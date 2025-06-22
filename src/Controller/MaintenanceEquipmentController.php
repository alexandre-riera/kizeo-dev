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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MaintenanceEquipmentController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Traiter les formulaires de maintenance par agence avec pagination pour éviter les timeouts
     */
    #[Route('/api/maintenance/process/{agencyCode}', name: 'app_maintenance_process_agency', methods: ['GET'])]
    public function processMaintenanceByAgency(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration pour éviter les timeouts
        ini_set('memory_limit', '1G');
        set_time_limit(300); // 5 minutes max
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        // Pagination pour éviter les gros volumes
        $limit = (int) $request->query->get('limit', 10); // Traiter 10 formulaires max par requête
        $offset = (int) $request->query->get('offset', 0);
        
        try {
            // 1. Récupérer les formulaires de maintenance pour l'agence
            $maintenanceForms = $this->getMaintenanceFormsByAgency($agencyCode, $limit, $offset);
            
            if (empty($maintenanceForms)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucun formulaire à traiter pour l\'agence ' . $agencyCode,
                    'processed' => 0,
                    'next_offset' => null
                ]);
            }

            $processed = 0;
            $errors = [];

            // 2. Traiter chaque formulaire individuellement
            foreach ($maintenanceForms as $form) {
                try {
                    $result = $this->processMaintenanceForm($form, $agencyCode, $entityManager);
                    $processed++;
                    
                    // Marquer comme lu après traitement réussi
                    $this->markFormAsRead($form['_form_id'], $form['_id']);
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'form_id' => $form['_form_id'],
                        'data_id' => $form['_id'],
                        'error' => $e->getMessage()
                    ];
                    error_log("Erreur traitement formulaire {$form['_form_id']}/{$form['_id']}: " . $e->getMessage());
                }
                
                // Libérer la mémoire après chaque formulaire
                gc_collect_cycles();
            }

            $nextOffset = count($maintenanceForms) === $limit ? $offset + $limit : null;

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'processed' => $processed,
                'errors' => $errors,
                'next_offset' => $nextOffset,
                'message' => $processed > 0 ? "Traitement terminé: {$processed} formulaires traités" : "Aucun formulaire traité"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du traitement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les formulaires de maintenance pour une agence spécifique
     */
    private function getMaintenanceFormsByAgency(string $agencyCode, int $limit, int $offset): array
    {
        try {
            // 1. Récupérer tous les formulaires de classe MAINTENANCE
            $response = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'timeout' => 30
            ]);

            $allForms = $response->toArray();
            $maintenanceForms = array_filter($allForms['forms'], function($form) {
                return $form['class'] === 'MAINTENANCE';
            });

            if (empty($maintenanceForms)) {
                return [];
            }

            $unreadData = [];
            
            // 2. Pour chaque formulaire de maintenance, récupérer les données non lues
            foreach ($maintenanceForms as $form) {
                try {
                    $formId = $form['id'];
                    
                    // Récupérer les données non lues avec action "lu"
                    $response = $this->client->request('POST', 
                        "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/unread/saved/{$limit}", [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 30
                    ]);

                    $unreadForms = $response->toArray();
                    
                    if (!empty($unreadForms['data'])) {
                        // Filtrer par agence et appliquer offset/limit
                        foreach ($unreadForms['data'] as $formData) {
                            // Récupérer les détails du formulaire pour vérifier l'agence
                            $detailResponse = $this->client->request('GET', 
                                "https://forms.kizeo.com/rest/v3/forms/{$formData['_form_id']}/data/{$formData['_id']}", [
                                'headers' => [
                                    'Accept' => 'application/json',
                                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                                ],
                                'timeout' => 20
                            ]);
                            
                            $formDetail = $detailResponse->toArray();
                            
                            // Vérifier si le formulaire appartient à l'agence demandée
                            if (isset($formDetail['data']['fields']['code_agence']['value']) && 
                                $formDetail['data']['fields']['code_agence']['value'] === $agencyCode) {
                                $unreadData[] = array_merge($formData, ['detail' => $formDetail['data']]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Erreur récupération formulaire {$formId}: " . $e->getMessage());
                    continue;
                }
            }

            // Appliquer l'offset et la limite
            return array_slice($unreadData, $offset, $limit);

        } catch (\Exception $e) {
            error_log("Erreur getMaintenanceFormsByAgency: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Traiter un formulaire de maintenance individuel
     */
    private function processMaintenanceForm(array $form, string $agencyCode, EntityManagerInterface $entityManager): array
    {
        $fields = $form['detail']['fields'];
        $entityClass = $this->getEntityClassByAgency($agencyCode);
        
        if (!$entityClass) {
            throw new \Exception("Classe d'entité non trouvée pour l'agence: {$agencyCode}");
        }

        $results = [
            'contract_equipments' => 0,
            'off_contract_equipments' => 0,
            'photos_processed' => 0
        ];

        // Traitement des équipements AU CONTRAT
        if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
            foreach ($fields['contrat_de_maintenance']['value'] as $equipmentContrat) {
                try {
                    $equipement = new $entityClass();
                    
                    // Données communes
                    $this->setCommonEquipmentData($equipement, $fields);
                    
                    // Données spécifiques équipement au contrat
                    $this->setContractEquipmentData($equipement, $equipmentContrat);
                    
                    // Traitement des photos
                    $this->processEquipmentPhotos($equipement, $equipmentContrat, $form);
                    
                    $entityManager->persist($equipement);
                    $results['contract_equipments']++;
                    
                } catch (\Exception $e) {
                    error_log("Erreur équipement contrat: " . $e->getMessage());
                }
            }
        }

        // Traitement des équipements HORS CONTRAT
        if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
            foreach ($fields['tableau2']['value'] as $equipmentHorsContrat) {
                try {
                    $equipement = new $entityClass();
                    
                    // Données communes
                    $this->setCommonEquipmentData($equipement, $fields);
                    
                    // Données spécifiques équipement hors contrat
                    $this->setOffContractEquipmentData($equipement, $equipmentHorsContrat, $fields, $entityClass, $entityManager);
                    
                    $entityManager->persist($equipement);
                    $results['off_contract_equipments']++;
                    
                } catch (\Exception $e) {
                    error_log("Erreur équipement hors contrat: " . $e->getMessage());
                }
            }
        }

        try {
            $entityManager->flush();
        } catch (\Exception $e) {
            error_log("Erreur flush: " . $e->getMessage());
            throw $e;
        }

        return $results;
    }

    /**
     * Définir les données communes à tous les équipements
     */
    private function setCommonEquipmentData($equipement, array $fields): void
    {
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_client']['value'] ?? '');
        $equipement->setDateEnregistrement($fields['date_et_heure1']['value'] ?? new \DateTime());
        $equipement->setCodeSociete($fields['id_societe']['value'] ?? '');
        $equipement->setCodeAgence($fields['id_agence']['value'] ?? '');
        $equipement->setDerniereVisite($fields['date_et_heure1']['value'] ?? new \DateTime());
        $equipement->setTrigrammeTech($fields['trigramme']['value'] ?? '');
        $equipement->setSignatureTech($fields['signature3']['value'] ?? '');
    }

    /**
     * Définir les données spécifiques aux équipements au contrat
     */
    private function setContractEquipmentData($equipement, array $equipmentContrat): void
    {
        // Extraction du numéro d'équipement depuis le path
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $equipementValue = $equipmentContrat['equipement']['value'] ?? '';
        
        // Détermination du type de visite
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        // Extraction des informations d'équipement
        $equipmentInfo = $this->parseEquipmentInfo($equipementValue);
        
        $equipement->setNumeroEquipement($equipmentInfo['numero'] ?? '');
        $equipement->setLibelleEquipement($equipmentInfo['libelle'] ?? '');
        $equipement->setMiseEnService($equipmentInfo['mise_en_service'] ?? '');
        $equipement->setNumeroDeSerie($equipmentInfo['numero_serie'] ?? '');
        $equipement->setMarque($equipmentInfo['marque'] ?? '');
        $equipement->setHauteur($equipmentInfo['hauteur'] ?? '');
        $equipement->setLargeur($equipmentInfo['largeur'] ?? '');
        $equipement->setRepereSiteClient($equipmentInfo['repere'] ?? '');
        
        // Données spécifiques
        $equipement->setModeFonctionnement($equipmentContrat['mode_fonctionnement']['value'] ?? '');
        $equipement->setLongueur($equipmentContrat['longueur']['value'] ?? 'NC');
        $equipement->setPlaqueSignaletique($equipmentContrat['plaque_signaletique']['value'] ?? '');
        $equipement->setEtat($equipmentContrat['etat']['value'] ?? '');
        
        // Statut de maintenance basé sur l'état
        $equipement->setStatutDeMaintenance($this->getMaintenanceStatusFromEtat($equipmentContrat['etat']['value'] ?? ''));
        
        $equipement->setEnMaintenance(true);
        $equipement->setIsArchive(false);
    }

    /**
     * Définir les données spécifiques aux équipements hors contrat
     */
    private function setOffContractEquipmentData($equipement, array $equipmentHorsContrat, array $fields, string $entityClass, EntityManagerInterface $entityManager): void
    {
        $typeLibelle = strtolower($equipmentHorsContrat['nature']['value'] ?? '');
        $typeCode = $this->getTypeCodeFromLibelle($typeLibelle);
        $idClient = $fields['id_client_']['value'] ?? '';
        
        // Génération automatique du numéro d'équipement
        $nouveauNumero = $this->getNextEquipmentNumber($typeCode, $idClient, $entityClass, $entityManager);
        $numeroFormate = $typeCode . str_pad($nouveauNumero, 2, '0', STR_PAD_LEFT);
        
        $equipement->setNumeroEquipement($numeroFormate);
        $equipement->setLibelleEquipement($typeLibelle);
        $equipement->setModeFonctionnement($equipmentHorsContrat['mode_fonctionnement_']['value'] ?? '');
        $equipement->setRepereSiteClient($equipmentHorsContrat['localisation_site_client1']['value'] ?? '');
        $equipement->setMiseEnService($equipmentHorsContrat['annee']['value'] ?? '');
        $equipement->setNumeroDeSerie($equipmentHorsContrat['n_de_serie']['value'] ?? '');
        $equipement->setMarque($equipmentHorsContrat['marque']['value'] ?? '');
        $equipement->setLargeur($equipmentHorsContrat['largeur']['value'] ?? '');
        $equipement->setHauteur($equipmentHorsContrat['hauteur']['value'] ?? '');
        $equipement->setPlaqueSignaletique($equipmentHorsContrat['plaque_signaletique1']['value'] ?? '');
        $equipement->setEtat($equipmentHorsContrat['etat1']['value'] ?? '');
        
        // Détermination du type de visite par défaut
        $equipement->setVisite($this->getDefaultVisitType($fields));
        $equipement->setStatutDeMaintenance($this->getMaintenanceStatusFromEtat($equipmentHorsContrat['etat1']['value'] ?? ''));
        
        $equipement->setEnMaintenance(false);
        $equipement->setIsArchive(false);
    }

    /**
     * Traitement des photos d'équipement
     */
    private function processEquipmentPhotos($equipement, array $equipmentData, array $form): void
    {
        $photoFields = [
            'photo_plaque', 'photo_etiquette_somafi', 'photo_choc', 'photo_choc_montant',
            'photo_panneau_intermediaire_i', 'photo_panneau_bas_inter_ext', 
            'photo_lame_basse__int_ext', 'photo_lame_intermediaire_int_',
            'photo_envirronement_eclairage', 'photo_bache', 'photo_marquage_au_sol',
            'photo_environnement_equipement1', 'photo_coffret_de_commande',
            'photo_carte', 'photo_rail', 'photo_equerre_rail', 'photo_fixation_coulisse',
            'photo_moteur', 'photo_deformation_plateau', 'photo_deformation_plaque',
            'photo_deformation_structure', 'photo_deformation_chassis',
            'photo_deformation_levre', 'photo_fissure_cordon', 'photo_joue',
            'photo_butoir', 'photo_vantail', 'photo_linteau', 'photo_marquage_au_sol_',
            'photo2'
        ];

        foreach ($photoFields as $photoField) {
            if (isset($equipmentData[$photoField]['value']) && !empty($equipmentData[$photoField]['value'])) {
                $methodName = 'set' . $this->camelize($photoField);
                if (method_exists($equipement, $methodName)) {
                    $equipement->$methodName($equipmentData[$photoField]['value']);
                }
            }
        }
    }

    /**
     * Marquer un formulaire comme lu
     */
    private function markFormAsRead(string $formId, string $dataId): void
    {
        try {
            $this->client->request('POST', 
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/markasreadbyaction/saved", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'json' => [
                    "data_ids" => [intval($dataId)]
                ],
                'timeout' => 10
            ]);
        } catch (\Exception $e) {
            error_log("Erreur markFormAsRead: " . $e->getMessage());
        }
    }

    // --- MÉTHODES UTILITAIRES ---

    private function getEntityClassByAgency(string $codeAgence): ?string
    {
        $agencyMap = [
            'S10' => EquipementS10::class, 'S40' => EquipementS40::class, 'S50' => EquipementS50::class,
            'S60' => EquipementS60::class, 'S70' => EquipementS70::class, 'S80' => EquipementS80::class,
            'S100' => EquipementS100::class, 'S120' => EquipementS120::class, 'S130' => EquipementS130::class,
            'S140' => EquipementS140::class, 'S150' => EquipementS150::class, 'S160' => EquipementS160::class,
            'S170' => EquipementS170::class,
        ];
        return $agencyMap[$codeAgence] ?? null;
    }

    private function extractVisitTypeFromPath(string $path): string
    {
        if (str_contains($path, 'CE1')) return 'CE1';
        if (str_contains($path, 'CE2')) return 'CE2';
        if (str_contains($path, 'CE3')) return 'CE3';
        if (str_contains($path, 'CE4')) return 'CE4';
        if (str_contains($path, 'CEA')) return 'CEA';
        return 'CE1'; // Par défaut
    }

    private function parseEquipmentInfo(string $equipmentValue): array
    {
        // Parse de la structure "ATEIS\CEA\SEC01|Porte sectionnelle|..."
        $parts = explode('|', $equipmentValue);
        
        return [
            'numero' => $parts[0] ?? '',
            'libelle' => $parts[1] ?? '',
            'mise_en_service' => $parts[2] ?? '',
            'numero_serie' => $parts[3] ?? '',
            'marque' => $parts[4] ?? '',
            'hauteur' => $parts[5] ?? '',
            'largeur' => $parts[6] ?? '',
            'repere' => $parts[7] ?? ''
        ];
    }

    private function getMaintenanceStatusFromEtat(string $etat): string
    {
        switch ($etat) {
            case "Rien à signaler le jour de la visite.":
            case "Fonctionnement ok":
                return "Vert";
            case "Travaux à prévoir":
                return "Orange";
            case "Travaux obligatoires":
            case "Equipement mis à l'arrêt lors de l'intervention":
                return "Rouge";
            case "Equipement inaccessible le jour de la visite":
                return "Inaccessible";
            case "Equipement à l'arrêt le jour de la visite":
                return "A l'arrêt";
            case "Equipement non présent sur site":
                return "Non présent";
            default:
                return "NC";
        }
    }

    private function getTypeCodeFromLibelle(string $libelle): string
    {
        $typeMap = [
            'porte sectionnelle' => 'SEC',
            'portail coulissant' => 'COU',
            'portail battant' => 'BAT',
            'barrière levante' => 'LEV',
            'porte basculante' => 'BAS',
            'rideau métallique' => 'RID',
            'porte rapide' => 'RAP',
            'tourniquets' => 'TOU',
            'sas' => 'SAS',
            'divers' => 'DIV'
        ];
        
        foreach ($typeMap as $key => $code) {
            if (str_contains($libelle, $key)) {
                return $code;
            }
        }
        
        return 'DIV'; // Par défaut
    }

    private function getNextEquipmentNumber(string $typeCode, string $idClient, string $entityClass, EntityManagerInterface $entityManager): int
    {
        $repository = $entityManager->getRepository($entityClass);
        
        $equipments = $repository->createQueryBuilder('e')
            ->where('e.id_contact = :idClient')
            ->andWhere('e.numero_equipement LIKE :typeCode')
            ->setParameter('idClient', $idClient)
            ->setParameter('typeCode', $typeCode . '%')
            ->getQuery()
            ->getResult();
        
        $lastNumber = 0;
        
        foreach ($equipments as $equipment) {
            $numeroEquipement = $equipment->getNumeroEquipement();
            
            if (preg_match('/^' . preg_quote($typeCode) . '(\d+)$/', $numeroEquipement, $matches)) {
                $number = (int)$matches[1];
                if ($number > $lastNumber) {
                    $lastNumber = $number;
                }
            }
        }
        
        return $lastNumber + 1;
    }

    private function getDefaultVisitType(array $fields): string
    {
        // Logique pour déterminer le type de visite par défaut
        // basée sur les équipements au contrat s'ils existent
        if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
            $firstEquipment = $fields['contrat_de_maintenance']['value'][0];
            return $this->extractVisitTypeFromPath($firstEquipment['equipement']['path'] ?? '');
        }
        
        return 'CE1'; // Par défaut
    }

    private function camelize(string $string): string
    {
        return str_replace('_', '', ucwords($string, '_'));
    }

    /**
     * Route pour obtenir le statut de traitement par agence
     */
    #[Route('/api/maintenance/status/{agencyCode}', name: 'app_maintenance_status', methods: ['GET'])]
    public function getAgencyMaintenanceStatus(string $agencyCode): JsonResponse
    {
        try {
            // Compter les formulaires non lus pour cette agence
            $unreadCount = $this->getUnreadFormsCount($agencyCode);
            
            return new JsonResponse([
                'agency' => $agencyCode,
                'unread_forms' => $unreadCount,
                'status' => $unreadCount > 0 ? 'pending' : 'up_to_date'
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la récupération du statut: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getUnreadFormsCount(string $agencyCode): int
    {
        // Implémentation simplifiée - dans la vraie vie, vous voudriez
        // peut-être mettre en cache cette information
        try {
            $forms = $this->getMaintenanceFormsByAgency($agencyCode, 100, 0);
            return count($forms);
        } catch (\Exception $e) {
            error_log("Erreur getUnreadFormsCount: " . $e->getMessage());
            return 0;
        }
    }
}