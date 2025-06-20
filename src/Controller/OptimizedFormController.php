<?php

namespace App\Controller;

use App\Entity\EquipementS10;
use App\Entity\EquipementS40;
use App\Entity\EquipementS50;
use App\Entity\EquipementS60;
use App\Entity\EquipementS70;
use App\Entity\EquipementS80;
use App\Entity\EquipementS100;
use App\Entity\EquipementS120;
use App\Entity\EquipementS130;
use App\Entity\EquipementS140;
use App\Entity\EquipementS150;
use App\Entity\EquipementS160;
use App\Entity\EquipementS170;
use App\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class OptimizedFormController extends AbstractController
{
    private const AGENCIES = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
    private const BATCH_SIZE = 3; // Petits lots pour éviter les timeouts
    private const MAX_EXECUTION_TIME = 840; // 14 minutes (garder 1 min de marge)
    
    private HttpClientInterface $client;
    private FormRepository $formRepository;

    public function __construct(HttpClientInterface $client, FormRepository $formRepository)
    {
        $this->client = $client;
        $this->formRepository = $formRepository;
    }

    /**
     * Route principale pour traiter toutes les agences avec gestion de reprise
     */
    #[Route('/api/forms/process/all-agencies', name: 'app_process_all_agencies', methods: ['GET'])]
    public function processAllAgencies(
        EntityManagerInterface $entityManager, 
        CacheInterface $cache,
        Request $request
    ): JsonResponse {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        
        $startAgency = $request->query->get('start_agency', 'S10');
        $continueFromFormIndex = $request->query->get('continue_from', 0);
        
        $globalStats = [
            'start_time' => time(),
            'current_agency' => $startAgency,
            'agencies_processed' => [],
            'total_equipment_processed' => 0,
            'total_errors' => 0,
            'last_position' => []
        ];
        
        $startProcessing = false;
        
        foreach (self::AGENCIES as $agency) {
            // Commencer à partir de l'agence spécifiée
            if (!$startProcessing && $agency !== $startAgency) {
                continue;
            }
            $startProcessing = true;
            
            $globalStats['current_agency'] = $agency;
            
            // Vérifier le timeout global
            if ((time() - $globalStats['start_time']) > self::MAX_EXECUTION_TIME) {
                $globalStats['timeout_reached'] = true;
                $globalStats['continue_url'] = $this->generateUrl('app_process_all_agencies', [
                    'start_agency' => $agency,
                    'continue_from' => 0
                ]);
                break;
            }
            
            try {
                $this->logProgress("🚀 DÉBUT traitement agence $agency");
                
                $agencyResult = $this->processAgencyOptimized(
                    $agency, 
                    $entityManager, 
                    $cache, 
                    $agency === $startAgency ? $continueFromFormIndex : 0
                );
                
                $globalStats['agencies_processed'][$agency] = $agencyResult;
                $globalStats['total_equipment_processed'] += $agencyResult['total_processed'];
                $globalStats['total_errors'] += $agencyResult['errors'];
                
                $this->logProgress("✅ TERMINÉ agence $agency - Équipements: {$agencyResult['total_processed']}, Erreurs: {$agencyResult['errors']}");
                
                // Sauvegarder la position actuelle
                $globalStats['last_position'] = [
                    'agency' => $agency,
                    'completed' => true
                ];
                
                // Pause entre les agences
                sleep(2);
                
            } catch (\Exception $e) {
                $this->logError("❌ ERREUR CRITIQUE agence $agency: " . $e->getMessage());
                $globalStats['agencies_processed'][$agency] = [
                    'error' => $e->getMessage(),
                    'total_processed' => 0,
                    'errors' => 1
                ];
                $globalStats['total_errors']++;
            }
        }
        
        $globalStats['execution_time'] = time() - $globalStats['start_time'];
        $this->logProgress("🎉 TRAITEMENT GLOBAL TERMINÉ - Total équipements: {$globalStats['total_equipment_processed']}, Erreurs: {$globalStats['total_errors']}");
        
        return new JsonResponse([
            'status' => 'completed',
            'global_stats' => $globalStats,
            'summary' => [
                'agencies_processed' => count($globalStats['agencies_processed']),
                'total_equipment' => $globalStats['total_equipment_processed'],
                'total_errors' => $globalStats['total_errors'],
                'execution_time_minutes' => round($globalStats['execution_time'] / 60, 2)
            ]
        ]);
    }

    /**
     * Route pour traiter une agence spécifique avec gestion de reprise
     */
    #[Route('/api/forms/process/agency/{agency}', name: 'app_process_agency', methods: ['GET'])]
    public function processAgency(
        string $agency,
        EntityManagerInterface $entityManager, 
        CacheInterface $cache,
        Request $request
    ): JsonResponse {
        if (!in_array($agency, self::AGENCIES)) {
            return new JsonResponse(['error' => 'Agence non valide'], 400);
        }
        
        $continueFromIndex = $request->query->get('continue_from', 0);
        $result = $this->processAgencyOptimized($agency, $entityManager, $cache, $continueFromIndex);
        
        return new JsonResponse([
            'status' => 'completed',
            'agency' => $agency,
            'result' => $result
        ]);
    }

    /**
     * Traitement optimisé d'une agence avec gestion de reprise
     */
    private function processAgencyOptimized(
        string $agency, 
        EntityManagerInterface $entityManager, 
        CacheInterface $cache, 
        int $continueFromIndex = 0
    ): array {
        $startTime = time();
        
        $stats = [
            'agency' => $agency,
            'processed_contract' => 0,
            'processed_off_contract' => 0,
            'total_processed' => 0,
            'errors' => 0,
            'processed_forms' => 0,
            'error_details' => [],
            'continue_from_index' => $continueFromIndex
        ];
        
        try {
            // Récupérer tous les formulaires de l'agence
            $this->logProgress("🔍 [$agency] Récupération de tous les formulaires...");
            $allForms = $this->getAllFormsForAgency($agency);
            $totalForms = count($allForms);
            
            $this->logProgress("📊 [$agency] $totalForms formulaires trouvés, début à l'index $continueFromIndex");
            
            if ($totalForms === 0) {
                $this->logProgress("⚠️ [$agency] Aucun formulaire trouvé");
                return $stats;
            }
            
            // Traiter à partir de l'index spécifié
            for ($i = $continueFromIndex; $i < $totalForms; $i++) {
                // Vérifier le timeout
                if ((time() - $startTime) > self::MAX_EXECUTION_TIME) {
                    $this->logProgress("⏰ [$agency] TIMEOUT atteint à l'index $i/$totalForms");
                    $stats['timeout_reached'] = true;
                    $stats['continue_url'] = $this->generateUrl('app_process_agency', [
                        'agency' => $agency,
                        'continue_from' => $i
                    ]);
                    break;
                }
                
                $formData = $allForms[$i];
                $this->logProgress("📝 [$agency] [$i/$totalForms] Traitement formulaire {$formData['form_id']}/{$formData['data_id']}");
                
                try {
                    $result = $this->processFormComplete($formData, $entityManager);
                    
                    $stats['processed_contract'] += $result['contract_equipment'];
                    $stats['processed_off_contract'] += $result['off_contract_equipment'];
                    $stats['total_processed'] += $result['contract_equipment'] + $result['off_contract_equipment'];
                    $stats['processed_forms']++;
                    
                    $this->logProgress("✅ [$agency] Formulaire traité - AU CONTRAT: {$result['contract_equipment']}, HORS CONTRAT: {$result['off_contract_equipment']}");
                    
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $errorDetail = [
                        'form_id' => $formData['form_id'],
                        'data_id' => $formData['data_id'],
                        'index' => $i,
                        'error' => $e->getMessage(),
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    $stats['error_details'][] = $errorDetail;
                    $this->logError("❌ [$agency] ERREUR formulaire {$formData['form_id']}: " . $e->getMessage());
                }
                
                // Pause entre les formulaires
                usleep(100000); // 0.1 seconde
            }
            
        } catch (\Exception $e) {
            $this->logError("💥 [$agency] ERREUR CRITIQUE: " . $e->getMessage());
            $stats['critical_error'] = $e->getMessage();
            $stats['errors']++;
        }
        
        $stats['execution_time'] = time() - $startTime;
        $this->logProgress("🎯 [$agency] TERMINÉ - Formulaires: {$stats['processed_forms']}, Équipements: {$stats['total_processed']}, Erreurs: {$stats['errors']}");
        
        return $stats;
    }

    /**
     * Récupération de tous les formulaires (LUS ET NON LUS) pour une agence
     */
    private function getAllFormsForAgency(string $agency): array
    {
        $allForms = [];
        
        try {
            // Récupérer tous les formulaires MAINTENANCE
            $response = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'timeout' => 30
            ]);
            
            $content = $response->toArray();
            $maintenanceForms = array_filter($content['forms'], function($form) {
                return $form['class'] == "MAINTENANCE";
            });

            foreach ($maintenanceForms as $form) {
                try {
                    // MODIFICATION: Récupérer TOUS les formulaires (pas seulement les non lus)
                    $response = $this->client->request('POST', 
                        "https://forms.kizeo.com/rest/v3/forms/{$form['id']}/data/advanced", [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 60
                    ]);

                    $result = $response->toArray();
                    
                    foreach ($result['data'] as $formData) {
                        // Vérifier si c'est la bonne agence
                        $details = $this->getFormDetails($formData['_form_id'], $formData['_id']);
                        if ($details && isset($details['fields']['code_agence']['value']) && 
                            $details['fields']['code_agence']['value'] === $agency) {
                            
                            $allForms[] = [
                                'form_id' => $formData['_form_id'],
                                'data_id' => $formData['_id']
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $this->logError("Erreur récupération formulaire {$form['id']}: " . $e->getMessage());
                    continue;
                }
            }
            
        } catch (\Exception $e) {
            $this->logError("Erreur récupération formulaires généraux: " . $e->getMessage());
        }
        
        return $allForms;
    }

    /**
     * Traitement complet d'un formulaire (photos + équipements)
     */
    private function processFormComplete(array $formData, EntityManagerInterface $entityManager): array
    {
        $formDetails = $this->getFormDetails($formData['form_id'], $formData['data_id']);
        
        if (!$formDetails || !isset($formDetails['fields'])) {
            throw new \Exception("Impossible de récupérer les détails du formulaire");
        }
        
        // Enregistrer les photos
        $this->uploadPicturesInDatabase($formDetails, $entityManager);
        
        // Traiter les équipements
        return $this->processFormEquipmentsDetailed($formDetails['fields'], $entityManager);
    }

    /**
     * Récupère les détails d'un formulaire
     */
    private function getFormDetails(string $formId, string $dataId): ?array
    {
        try {
            $response = $this->client->request('GET', 
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/{$dataId}", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'timeout' => 30
            ]);

            $result = $response->toArray();
            return $result['data'] ?? null;
        } catch (\Exception $e) {
            throw new \Exception("Erreur récupération détails formulaire {$formId}/{$dataId}: " . $e->getMessage());
        }
    }

    /**
     * Enregistrement des photos d'un formulaire
     */
    private function uploadPicturesInDatabase(array $formData, EntityManagerInterface $entityManager): void
    {
        try {
            // Traiter les équipements AU CONTRAT
            if (isset($formData['fields']['contrat_de_maintenance']['value'])) {
                foreach ($formData['fields']['contrat_de_maintenance']['value'] as $additionalEquipment) {
                    $this->saveEquipmentPictures($formData, $additionalEquipment, null, $entityManager);
                }
            }
            
            // Traiter les équipements HORS CONTRAT
            if (isset($formData['fields']['tableau2']['value'])) {
                foreach ($formData['fields']['tableau2']['value'] as $equipmentSupplementaire) {
                    $this->saveEquipmentPictures($formData, null, $equipmentSupplementaire, $entityManager);
                }
            }
            
            $entityManager->flush();
            
        } catch (\Exception $e) {
            $this->logError("Erreur sauvegarde photos: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sauvegarde des photos d'un équipement
     */
    private function saveEquipmentPictures(array $formData, ?array $contractEquipment, ?array $offContractEquipment, EntityManagerInterface $entityManager): void
    {
        $equipement = new \App\Entity\Form();

        $equipement->setFormId($formData['form_id']);
        $equipement->setDataId($formData['id']);
        $equipement->setUpdateTime($formData['update_time']);

        if ($contractEquipment) {
            // Équipement AU CONTRAT
            $equipement->setCodeEquipement($contractEquipment['equipement']['value']);
            $equipement->setRaisonSocialeVisite($contractEquipment['equipement']['path']);
            
            if (isset($contractEquipment['photo_etiquette_somafi']['value'])) {
                $equipement->setPhotoEtiquetteSomafi($contractEquipment['photo_etiquette_somafi']['value']);
            }
            $equipement->setPhotoPlaque($contractEquipment['photo_plaque']['value'] ?? '');
            $equipement->setPhotoChoc($contractEquipment['photo_choc']['value'] ?? '');
            
            $this->setContractEquipmentPhotos($equipement, $contractEquipment);
            
        } elseif ($offContractEquipment) {
            // Équipement HORS CONTRAT
            $equipement->setRaisonSocialeVisite($formData['fields']['contrat_de_maintenance']['value'][0]['equipement']['path'] ?? '');
            $equipement->setPhotoCompteRendu($offContractEquipment['photo3']['value'] ?? '');
        }

        $entityManager->persist($equipement);
    }

    /**
     * Définition de toutes les photos des équipements au contrat
     */
    private function setContractEquipmentPhotos($equipement, array $contractEquipment): void
    {
        $photoFields = [
            'photo_choc_tablier_porte', 'photo_choc_tablier', 'photo_axe', 'photo_serrure',
            'photo_serrure1', 'photo_feux', 'photo_panneau_intermediaire_i', 'photo_panneau_bas_inter_ext',
            'photo_lame_basse_int_ext', 'photo_lame_intermediaire_int_', 'photo_environnement_equipemen1',
            'photo_coffret_de_commande', 'photo_carte', 'photo_rail', 'photo_equerre_rail',
            'photo_fixation_coulisse', 'photo_moteur', 'photo_deformation_plateau', 'photo_deformation_plaque',
            'photo_deformation_structure', 'photo_deformation_chassis', 'photo_deformation_levre',
            'photo_fissure_cordon', 'photo_joue', 'photo_butoir', 'photo_vantail', 'photo_linteau',
            'photo_marquage_au_sol_', 'photo2'
        ];

        foreach ($photoFields as $field) {
            if (isset($contractEquipment[$field]['value'])) {
                $methodName = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
                if (method_exists($equipement, $methodName)) {
                    $equipement->$methodName($contractEquipment[$field]['value']);
                }
            }
        }
    }

    /**
     * Traitement détaillé des équipements avec comptage
     */
    private function processFormEquipmentsDetailed(array $fields, EntityManagerInterface $entityManager): array
    {
        $results = [
            'contract_equipment' => 0,
            'off_contract_equipment' => 0
        ];
        
        if (!isset($fields['code_agence']['value'])) {
            return $results;
        }

        $entityClass = $this->getEntityClassByAgency($fields['code_agence']['value']);
        if (!$entityClass) {
            return $results;
        }

        // Traitement des équipements AU CONTRAT
        if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
            $contractCount = $this->processContractEquipments($fields, $entityClass, $entityManager);
            $results['contract_equipment'] = $contractCount;
        }

        // Traitement des équipements HORS CONTRAT
        if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
            $offContractCount = $this->processOffContractEquipments($fields, $entityClass, $entityManager);
            $results['off_contract_equipment'] = $offContractCount;
        }
        
        return $results;
    }

    /**
     * Traitement équipements au contrat avec comptage
     */
    private function processContractEquipments(array $fields, string $entityClass, EntityManagerInterface $entityManager): int
    {
        $count = 0;
        
        foreach ($fields['contrat_de_maintenance']['value'] as $additionalEquipment) {
            try {
                $equipement = new $entityClass();
                
                $this->setCommonEquipmentData($equipement, $fields);
                
                $equipement->setNumeroEquipement($additionalEquipment['equipement']['value']);
                $equipement->setIfExistDB($additionalEquipment['equipement']['columns']);
                $equipement->setLibelleEquipement(strtolower($additionalEquipment['reference7']['value']));
                $equipement->setModeFonctionnement($additionalEquipment['mode_fonctionnement_2']['value']);
                $equipement->setRepereSiteClient($additionalEquipment['localisation_site_client']['value']);
                $equipement->setMiseEnService($additionalEquipment['reference2']['value']);
                $equipement->setNumeroDeSerie($additionalEquipment['reference6']['value']);
                $equipement->setMarque($additionalEquipment['reference5']['value']);
                
                $equipement->setLargeur($additionalEquipment['reference3']['value'] ?? '');
                $equipement->setHauteur($additionalEquipment['reference1']['value'] ?? '');
                $equipement->setLongueur($additionalEquipment['longueur']['value'] ?? 'NC');
                
                $equipement->setPlaqueSignaletique($additionalEquipment['plaque_signaletique']['value']);
                $equipement->setEtat($additionalEquipment['etat']['value']);
                
                $equipement->setHauteurNacelle($additionalEquipment['hauteur_de_nacelle_necessaire']['value'] ?? '');
                $equipement->setModeleNacelle($additionalEquipment['si_location_preciser_le_model']['value'] ?? '');
                
                $equipement->setStatutDeMaintenance($this->getMaintenanceContractStatus($additionalEquipment['etat']['value']));
                $equipement->setVisite($this->getVisitType($additionalEquipment['equipement']['path']));
                
                $equipement->setEnMaintenance(true);
                $equipement->setIsArchive(false);
                
                $entityManager->persist($equipement);
                $count++;
                
            } catch (\Exception $e) {
                $this->logError("Erreur équipement au contrat: " . $e->getMessage());
                continue;
            }
        }
        
        $entityManager->flush();
        return $count;
    }

    /**
     * Traitement équipements hors contrat avec comptage
     */
    private function processOffContractEquipments(array $fields, string $entityClass, EntityManagerInterface $entityManager): int
    {
        $count = 0;
        
        foreach ($fields['tableau2']['value'] as $equipementsHorsContrat) {
            try {
                $equipement = new $entityClass();
                
                $this->setCommonEquipmentData($equipement, $fields);
                
                // Attribution automatique du numéro d'équipement
                $typeLibelle = strtolower($equipementsHorsContrat['nature']['value']);
                $typeCode = $this->getTypeCodeFromLibelle($typeLibelle);
                $idClient = $fields['id_client_']['value'];
                $nouveauNumero = $this->getNextEquipmentNumberFromDatabase($typeCode, $idClient, $entityClass, $entityManager);
                $numeroFormate = $typeCode . str_pad($nouveauNumero, 2, '0', STR_PAD_LEFT);
                
                $equipement->setNumeroEquipement($numeroFormate);
                $equipement->setLibelleEquipement($typeLibelle);
                $equipement->setModeFonctionnement($equipementsHorsContrat['mode_fonctionnement_']['value']);
                $equipement->setRepereSiteClient($equipementsHorsContrat['localisation_site_client1']['value']);
                $equipement->setMiseEnService($equipementsHorsContrat['annee']['value']);
                $equipement->setNumeroDeSerie($equipementsHorsContrat['n_de_serie']['value']);
                $equipement->setMarque($equipementsHorsContrat['marque']['value']);
                
                $equipement->setLargeur($equipementsHorsContrat['largeur']['value'] ?? '');
                $equipement->setHauteur($equipementsHorsContrat['hauteur']['value'] ?? '');
                
                $equipement->setPlaqueSignaletique($equipementsHorsContrat['plaque_signaletique1']['value']);
                $equipement->setEtat($equipementsHorsContrat['etat1']['value']);
                
                $equipement->setStatutDeMaintenance($this->getOffContractMaintenanceStatus($equipementsHorsContrat['etat1']['value']));
                $equipement->setVisite($this->getDefaultVisitType($fields));
                
                $equipement->setEnMaintenance(false);
                $equipement->setIsArchive(false);
                
                $entityManager->persist($equipement);
                $count++;
                
            } catch (\Exception $e) {
                $this->logError("Erreur équipement hors contrat: " . $e->getMessage());
                continue;
            }
        }
        
        $entityManager->flush();
        return $count;
    }

    /**
     * Définition des données communes à tous les équipements
     */
    private function setCommonEquipmentData($equipement, array $fields): void
    {
        $equipement->setIdContact($fields['id_client_']['value']);
        $equipement->setRaisonSociale($fields['nom_client']['value']);
        $equipement->setDateEnregistrement($fields['date_et_heure1']['value']);
        $equipement->setCodeSociete($fields['id_societe']['value'] ?? '');
        $equipement->setCodeAgence($fields['id_agence']['value'] ?? '');
        $equipement->setDerniereVisite($fields['date_et_heure1']['value']);
        $equipement->setTrigrammeTech($fields['trigramme']['value']);
        $equipement->setSignatureTech($fields['signature3']['value']);
        
        if (isset($fields['test_']['value'])) {
            $equipement->setTest($fields['test_']['value']);
        }
    }

    /**
     * Détermination de la classe d'entité selon le code agence
     */
    private function getEntityClassByAgency(string $codeAgence): ?string
    {
        $agencyMap = [
            'S10' => EquipementS10::class,
            'S40' => EquipementS40::class,
            'S50' => EquipementS50::class,
            'S60' => EquipementS60::class,
            'S70' => EquipementS70::class,
            'S80' => EquipementS80::class,
            'S100' => EquipementS100::class,
            'S120' => EquipementS120::class,
            'S130' => EquipementS130::class,
            'S140' => EquipementS140::class,
            'S150' => EquipementS150::class,
            'S160' => EquipementS160::class,
            'S170' => EquipementS170::class,
        ];

        return $agencyMap[$codeAgence] ?? null;
    }

    /**
     * Détermine le prochain numéro d'équipement à utiliser
     */
    private function getNextEquipmentNumberFromDatabase(string $typeCode, string $idClient, string $entityClass, EntityManagerInterface $entityManager): int
    {
        $equipements = $entityManager->getRepository($entityClass)
            ->createQueryBuilder('e')
            ->where('e.idContact = :idClient')
            ->andWhere('e.numeroEquipement LIKE :pattern')
            ->setParameter('idClient', $idClient)
            ->setParameter('pattern', $typeCode . '%')
            ->getQuery()
            ->getResult();
        
        $dernierNumero = 0;
        
        foreach ($equipements as $equipement) {
            $numeroEquipement = $equipement->getNumeroEquipement();
            
            if (preg_match('/^' . preg_quote($typeCode) . '(\d+)$/', $numeroEquipement, $matches)) {
                $numero = (int)$matches[1];
                if ($numero > $dernierNumero) {
                    $dernierNumero = $numero;
                }
            }
        }
        
        return $dernierNumero + 1;
    }

    // Méthodes utilitaires pour les statuts et types
    private function getVisitType(string $equipmentPath): string
    {
        if (str_contains($equipmentPath, 'CE1')) return 'CE1';
        if (str_contains($equipmentPath, 'CE2')) return 'CE2';
        if (str_contains($equipmentPath, 'CE3')) return 'CE3';
        if (str_contains($equipmentPath, 'CE4')) return 'CE4';
        if (str_contains($equipmentPath, 'CEA')) return 'CEA';
        
        return 'CE1';
    }

    private function getDefaultVisitType(array $fields): string
    {
        if (!empty($fields['contrat_de_maintenance']['value'])) {
            return $this->getVisitType($fields['contrat_de_maintenance']['value'][0]['equipement']['path']);
        }
        return 'CE1';
    }

    private function getMaintenanceContractStatus(string $etat): string
    {
        switch ($etat) {
            case "Rien à signaler le jour de la visite. Fonctionnement ok":
                return "Vert";
            case "Travaux à prévoir":
                return "Orange";
            case "Travaux obligatoires":
                return "Rouge";
            case "Equipement inaccessible le jour de la visite":
                return "Inaccessible";
            case "Equipement à l'arrêt le jour de la visite":
                return "A l'arrêt";
            case "Equipement mis à l'arrêt lors de l'intervention":
                return "Rouge";
            case "Equipement non présent sur site":
                return "Non présent";
            default:
                return "NC";
        }
    }

    private function getOffContractMaintenanceStatus(string $etat): string
    {
        switch ($etat) {
            case "A":
                return "Bon état de fonctionnement le jour de la visite";
            case "B":
                return "Travaux préventifs";
            case "C":
                return "Travaux curatifs";
            case "D":
                return "Equipement à l'arrêt le jour de la visite";
            case "E":
                return "Equipement mis à l'arrêt lors de l'intervention";
            default:
                return "NC";
        }
    }

    private function getTypeCodeFromLibelle(string $typeLibelle): string
    {
        $typeCodeMap = [
            'porte sectionnelle' => 'SEC',
            'porte battante' => 'BPA',
            'porte basculante' => 'PBA',
            'porte rapide' => 'RAP',
            'porte pietonne' => 'PPV',
            'porte coulissante' => 'COU',
            'porte coupe feu' => 'CFE',
            'porte coupe-feu' => 'CFE',
            'porte accordéon' => 'PAC',
            'porte frigorifique' => 'COF',
            'barriere levante' => 'BLE',
            'barriere' => 'BLE',
            'mini pont' => 'MIP',
            'mini-pont' => 'MIP',
            'rideau' => 'RID',
            'rideau métalliques' => 'RID',
            'rideau metallique' => 'RID',
            'rideau métallique' => 'RID',
            'niveleur' => 'NIV',
            'portail' => 'PAU',
            'portail motorisé' => 'PMO',
            'portail motorise' => 'PMO',
            'portail manuel' => 'PMA',
            'portail coulissant' => 'PCO',
            'protection' => 'PRO',
            'portillon' => 'POR',
            'table elevatrice' => 'TEL',
            'tourniquet' => 'TOU',
            'issue de secours' => 'BPO',
            'bloc roue' => 'BLR',
            'sas' => 'SAS',
            'plaque de quai' => 'PLQ',
        ];

        $typeLibelle = strtolower(trim($typeLibelle));
        
        if (isset($typeCodeMap[$typeLibelle])) {
            return $typeCodeMap[$typeLibelle];
        }
        
        // Si le libellé contient plusieurs mots, prendre les premières lettres
        $words = explode(' ', $typeLibelle);
        if (count($words) > 1) {
            $code = '';
            foreach ($words as $word) {
                if (strlen($word) > 0) {
                    $code .= strtoupper(substr($word, 0, 1));
                }
            }
            if (strlen($code) < 3 && strlen($words[0]) >= 3) {
                $code = strtoupper(substr($words[0], 0, 3));
            }
            return $code;
        }
        
        return strtoupper(substr($typeLibelle, 0, 3));
    }

    /**
     * Route pour obtenir le statut du traitement
     */
    #[Route('/api/forms/status/{agency?}', name: 'app_forms_status', methods: ['GET'])]
    public function getFormsStatus(?string $agency = null): JsonResponse
    {
        try {
            $stats = ['agencies' => []];
            
            $agenciesToCheck = $agency ? [$agency] : self::AGENCIES;
            
            foreach ($agenciesToCheck as $agencyCode) {
                if (!in_array($agencyCode, self::AGENCIES)) {
                    continue;
                }
                
                $agencyStats = [
                    'agency' => $agencyCode,
                    'total_forms' => 0,
                    'entity_class' => $this->getEntityClassByAgency($agencyCode)
                ];
                
                try {
                    $allForms = $this->getAllFormsForAgency($agencyCode);
                    $agencyStats['total_forms'] = count($allForms);
                } catch (\Exception $e) {
                    $agencyStats['error'] = $e->getMessage();
                }
                
                $stats['agencies'][$agencyCode] = $agencyStats;
            }
            
            return new JsonResponse($stats);
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la récupération du statut: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Route pour nettoyer et reprendre le traitement depuis un point spécifique
     */
    #[Route('/api/forms/resume/{agency}/{fromIndex?}', name: 'app_forms_resume', methods: ['GET'])]
    public function resumeProcessing(
        string $agency, 
        EntityManagerInterface $entityManager, 
        CacheInterface $cache
        ?int $fromIndex = 0,
    ): JsonResponse {
        if (!in_array($agency, self::AGENCIES)) {
            return new JsonResponse(['error' => 'Agence non valide'], 400);
        }
        
        $this->logProgress("🔄 REPRISE traitement agence $agency depuis l'index $fromIndex");
        
        try {
            $result = $this->processAgencyOptimized($agency, $entityManager, $cache, $fromIndex);
            
            return new JsonResponse([
                'status' => 'completed',
                'agency' => $agency,
                'resumed_from_index' => $fromIndex,
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            $this->logError("Erreur reprise traitement $agency: " . $e->getMessage());
            return new JsonResponse([
                'status' => 'error',
                'agency' => $agency,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Route pour vider la base de données d'une agence (utile pour les tests)
     */
    #[Route('/api/forms/clear/{agency}', name: 'app_forms_clear_agency', methods: ['DELETE'])]
    public function clearAgencyData(string $agency, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!in_array($agency, self::AGENCIES)) {
            return new JsonResponse(['error' => 'Agence non valide'], 400);
        }
        
        try {
            $entityClass = $this->getEntityClassByAgency($agency);
            if (!$entityClass) {
                return new JsonResponse(['error' => 'Classe d\'entité non trouvée pour l\'agence'], 400);
            }
            
            $repository = $entityManager->getRepository($entityClass);
            $allEquipments = $repository->findAll();
            
            $count = count($allEquipments);
            
            foreach ($allEquipments as $equipment) {
                $entityManager->remove($equipment);
            }
            
            $entityManager->flush();
            
            $this->logProgress("🗑️ [$agency] $count équipements supprimés");
            
            return new JsonResponse([
                'status' => 'success',
                'agency' => $agency,
                'deleted_count' => $count,
                'message' => "Tous les équipements de l'agence $agency ont été supprimés"
            ]);
            
        } catch (\Exception $e) {
            $this->logError("Erreur suppression données $agency: " . $e->getMessage());
            return new JsonResponse([
                'status' => 'error',
                'agency' => $agency,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Route de diagnostic pour identifier les problèmes
     */
    #[Route('/api/forms/diagnostic/{agency?}', name: 'app_forms_diagnostic', methods: ['GET'])]
    public function diagnosticAgency(?string $agency = null): JsonResponse
    {
        $agenciesToCheck = $agency ? [$agency] : array_slice(self::AGENCIES, 0, 3); // Limiter à 3 pour le diagnostic
        
        $diagnostics = [
            'timestamp' => date('Y-m-d H:i:s'),
            'agencies' => []
        ];
        
        foreach ($agenciesToCheck as $agencyCode) {
            if (!in_array($agencyCode, self::AGENCIES)) {
                continue;
            }
            
            $agencyDiag = [
                'agency' => $agencyCode,
                'forms_found' => 0,
                'contract_equipment_total' => 0,
                'off_contract_equipment_total' => 0,
                'sample_forms' => [],
                'errors' => []
            ];
            
            try {
                $forms = $this->getAllFormsForAgency($agencyCode);
                $agencyDiag['forms_found'] = count($forms);
                
                // Analyser un échantillon de formulaires
                $sampleSize = min(3, count($forms));
                for ($i = 0; $i < $sampleSize; $i++) {
                    try {
                        $formData = $forms[$i];
                        $details = $this->getFormDetails($formData['form_id'], $formData['data_id']);
                        
                        if ($details && isset($details['fields'])) {
                            $contractCount = isset($details['fields']['contrat_de_maintenance']['value']) 
                                ? count($details['fields']['contrat_de_maintenance']['value']) : 0;
                            $offContractCount = isset($details['fields']['tableau2']['value']) 
                                ? count($details['fields']['tableau2']['value']) : 0;
                            
                            $agencyDiag['contract_equipment_total'] += $contractCount;
                            $agencyDiag['off_contract_equipment_total'] += $offContractCount;
                            
                            $agencyDiag['sample_forms'][] = [
                                'form_id' => $formData['form_id'],
                                'data_id' => $formData['data_id'],
                                'client_name' => $details['fields']['nom_client']['value'] ?? 'N/A',
                                'contract_equipment' => $contractCount,
                                'off_contract_equipment' => $offContractCount,
                                'visit_date' => $details['fields']['date_et_heure1']['value'] ?? 'N/A'
                            ];
                        }
                    } catch (\Exception $e) {
                        $agencyDiag['errors'][] = [
                            'form_index' => $i,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
            } catch (\Exception $e) {
                $agencyDiag['critical_error'] = $e->getMessage();
            }
            
            $diagnostics['agencies'][$agencyCode] = $agencyDiag;
        }
        
        return new JsonResponse($diagnostics);
    }

    /**
     * Logging et gestion des erreurs
     */
    private function logProgress(string $message): void
    {
        error_log("[" . date('Y-m-d H:i:s') . "] " . $message);
    }

    private function logError(string $message): void
    {
        error_log("[ERROR " . date('Y-m-d H:i:s') . "] " . $message);
    }
}