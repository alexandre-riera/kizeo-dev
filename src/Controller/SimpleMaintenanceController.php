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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SimpleMaintenanceController extends AbstractController
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @Route("/api/forms/process/simple/{agency}", name="app_process_simple_agency", methods={"GET"})
     */
    public function processSimpleAgency(
        string $agency,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration mémoire
        ini_set('memory_limit', '512M');
        set_time_limit(60);
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agency, $validAgencies)) {
            return new JsonResponse(['error' => 'Agence non valide: ' . $agency], 400);
        }

        $offset = (int)$request->query->get('offset', 0);
        $startTime = time();
        
        $result = [
            'agency' => $agency,
            'offset' => $offset,
            'processed' => false,
            'contract_equipment' => 0,
            'off_contract_equipment' => 0,
            'has_more' => false,
            'next_offset' => null,
            'next_url' => null,
            'memory_before' => memory_get_usage(true),
            'memory_after' => 0,
            'execution_time' => 0,
            'error' => null
        ];

        try {
            // ÉTAPE 1: Trouver le formulaire à la position offset
            $targetForm = $this->findFormAtOffset($agency, $offset);
            
            if (!$targetForm) {
                $result['message'] = "Aucun formulaire trouvé à l'offset $offset pour l'agence $agency";
                $result['memory_after'] = memory_get_usage(true);
                $result['execution_time'] = time() - $startTime;
                return new JsonResponse($result);
            }

            // ÉTAPE 2: Traiter ce formulaire
            $formDetails = $this->getFormDetails($targetForm['form_id'], $targetForm['data_id']);
            
            if ($formDetails && isset($formDetails['fields'])) {
                // Vérifier que c'est bien la bonne agence
                if ($formDetails['fields']['code_agence']['value'] === $agency) {
                    
                    // Traiter les équipements
                    $equipmentResults = $this->processFormEquipments($formDetails['fields'], $entityManager, $agency);
                    
                    $result['processed'] = true;
                    $result['contract_equipment'] = $equipmentResults['contract'];
                    $result['off_contract_equipment'] = $equipmentResults['off_contract'];
                    $result['client_name'] = $formDetails['fields']['nom_client']['value'] ?? 'N/A';
                    $result['visit_date'] = $formDetails['fields']['date_et_heure1']['value'] ?? 'N/A';
                    
                    // Marquer comme lu
                    $this->markFormAsRead($targetForm['form_id'], $targetForm['data_id']);
                    
                    // Nettoyer la mémoire
                    $entityManager->clear();
                    unset($formDetails, $equipmentResults);
                    gc_collect_cycles();
                }
            }

            // ÉTAPE 3: Vérifier s'il y a un formulaire suivant
            $nextForm = $this->findFormAtOffset($agency, $offset + 1);
            $result['has_more'] = ($nextForm !== null);
            
            if ($result['has_more']) {
                $result['next_offset'] = $offset + 1;
                $result['next_url'] = $this->generateUrl('app_process_simple_agency', [
                    'agency' => $agency,
                    'offset' => $offset + 1
                ]);
            }
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            error_log("Erreur traitement formulaire {$agency}:{$offset} - " . $e->getMessage());
        }
        
        $result['memory_after'] = memory_get_usage(true);
        $result['execution_time'] = time() - $startTime;
        
        return new JsonResponse($result);
    }

    /**
     * @Route("/api/forms/count/simple/{agency}", name="app_count_simple_agency", methods={"GET"})
     */
    public function countSimpleAgency(string $agency): JsonResponse
    {
        ini_set('memory_limit', '256M');
        set_time_limit(30);
        
        $count = 0;
        $sampleForms = [];
        
        try {
            // Récupérer les formulaires de maintenance
            $response = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'timeout' => 20
            ]);
            
            $content = $response->toArray();
            $maintenanceForms = array_filter($content['forms'], function($form) {
                return $form['class'] == "MAINTENANCE";
            });

            foreach ($maintenanceForms as $form) {
                try {
                    $response = $this->client->request('GET', 
                        "https://forms.kizeo.com/rest/v3/forms/{$form['id']}/data/unread/bienlu/50", [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 10
                    ]);

                    $result = $response->toArray();
                    
                    foreach ($result['data'] as $formData) {
                        if ($this->quickAgencyCheck($formData['_form_id'], $formData['_id'], $agency)) {
                            $count++;
                            
                            // Garder un échantillon des premiers formulaires
                            if (count($sampleForms) < 5) {
                                $sampleForms[] = [
                                    'form_id' => $formData['_form_id'],
                                    'data_id' => $formData['_id']
                                ];
                            }
                        }
                        
                        // Limite pour éviter timeout
                        if ($count >= 100) break 2;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
        
        return new JsonResponse([
            'agency' => $agency,
            'forms_count' => $count,
            'sample_forms' => $sampleForms,
            'estimated_time_minutes' => ceil($count * 0.5), // 30 secondes par formulaire
            'first_form_url' => $count > 0 ? $this->generateUrl('app_process_simple_agency', [
                'agency' => $agency,
                'offset' => 0
            ]) : null
        ]);
    }

    private function findFormAtOffset(string $agency, int $targetOffset): ?array
    {
        $currentIndex = 0;
        
        try {
            $response = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'timeout' => 20
            ]);
            
            $content = $response->toArray();
            $maintenanceForms = array_filter($content['forms'], function($form) {
                return $form['class'] == "MAINTENANCE";
            });

            foreach ($maintenanceForms as $form) {
                try {
                    $response = $this->client->request('GET', 
                        "https://forms.kizeo.com/rest/v3/forms/{$form['id']}/data/unread/bienlu/20", [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 10
                    ]);

                    $result = $response->toArray();
                    
                    foreach ($result['data'] as $formData) {
                        if ($this->quickAgencyCheck($formData['_form_id'], $formData['_id'], $agency)) {
                            if ($currentIndex === $targetOffset) {
                                return [
                                    'form_id' => $formData['_form_id'],
                                    'data_id' => $formData['_id']
                                ];
                            }
                            $currentIndex++;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
        } catch (\Exception $e) {
            error_log("Erreur findFormAtOffset: " . $e->getMessage());
        }
        
        return null;
    }

    private function quickAgencyCheck(string $formId, string $dataId, string $expectedAgency): bool
    {
        try {
            $response = $this->client->request('GET', 
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/{$dataId}", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'timeout' => 5
            ]);

            $result = $response->toArray();
            
            return isset($result['data']['fields']['code_agence']['value']) && 
                   $result['data']['fields']['code_agence']['value'] === $expectedAgency;
                   
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getFormDetails(string $formId, string $dataId): ?array
    {
        try {
            $response = $this->client->request('GET', 
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/{$dataId}", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'timeout' => 15
            ]);

            $result = $response->toArray();
            return $result['data'] ?? null;
        } catch (\Exception $e) {
            error_log("Erreur getFormDetails: " . $e->getMessage());
            return null;
        }
    }

    private function processFormEquipments(array $fields, EntityManagerInterface $entityManager, string $agency): array
    {
        $results = ['contract' => 0, 'off_contract' => 0];
        
        $entityClass = $this->getEntityClassByAgency($agency);
        if (!$entityClass) {
            return $results;
        }

        // Traitement équipements AU CONTRAT
        if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
            foreach ($fields['contrat_de_maintenance']['value'] as $additionalEquipment) {
                try {
                    $equipement = new $entityClass();
                    $this->setCommonEquipmentData($equipement, $fields);
                    $this->setContractEquipmentData($equipement, $additionalEquipment);
                    
                    $entityManager->persist($equipement);
                    $results['contract']++;
                } catch (\Exception $e) {
                    error_log("Erreur équipement contrat: " . $e->getMessage());
                }
            }
        }

        // Traitement équipements HORS CONTRAT
        if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
            foreach ($fields['tableau2']['value'] as $equipementsHorsContrat) {
                try {
                    $equipement = new $entityClass();
                    $this->setCommonEquipmentData($equipement, $fields);
                    $this->setOffContractEquipmentData($equipement, $equipementsHorsContrat, $fields, $entityClass, $entityManager);
                    
                    $entityManager->persist($equipement);
                    $results['off_contract']++;
                } catch (\Exception $e) {
                    error_log("Erreur équipement hors contrat: " . $e->getMessage());
                }
            }
        }

        try {
            $entityManager->flush();
        } catch (\Exception $e) {
            error_log("Erreur flush: " . $e->getMessage());
        }
        
        return $results;
    }

    private function markFormAsRead(string $formId, string $dataId): void
    {
        try {
            $this->client->request('POST', 
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/markasreadbyaction/bienlu", [
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

    private function setContractEquipmentData($equipement, array $additionalEquipment): void
    {
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
    }

    private function setOffContractEquipmentData($equipement, array $equipementsHorsContrat, array $fields, string $entityClass, EntityManagerInterface $entityManager): void
    {
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
    }

    private function getMaintenanceContractStatus(string $etat): string
    {
        switch ($etat) {
            case "Rien à signaler le jour de la visite. Fonctionnement ok": return "Vert";
            case "Travaux à prévoir": return "Orange";
            case "Travaux obligatoires": return "Rouge";
            case "Equipement inaccessible le jour de la visite": return "Inaccessible";
            case "Equipement à l'arrêt le jour de la visite": return "A l'arrêt";
            case "Equipement mis à l'arrêt lors de l'intervention": return "Rouge";
            case "Equipement non présent sur site": return "Non présent";
            default: return "NC";
        }
    }

    private function getOffContractMaintenanceStatus(string $etat): string
    {
        switch ($etat) {
            case "A": return "Bon état de fonctionnement le jour de la visite";
            case "B": return "Travaux préventifs";
            case "C": return "Travaux curatifs";
            case "D": return "Equipement à l'arrêt le jour de la visite";
            case "E": return "Equipement mis à l'arrêt lors de l'intervention";
            default: return "NC";
        }
    }

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

    private function getTypeCodeFromLibelle(string $typeLibelle): string
    {
        $typeCodeMap = [
            'porte sectionnelle' => 'SEC', 'porte battante' => 'BPA', 'porte basculante' => 'PBA',
            'porte rapide' => 'RAP', 'porte pietonne' => 'PPV', 'porte coulissante' => 'COU',
            'porte coupe feu' => 'CFE', 'porte coupe-feu' => 'CFE', 'porte accordéon' => 'PAC',
            'porte frigorifique' => 'COF', 'barriere levante' => 'BLE', 'barriere' => 'BLE',
            'mini pont' => 'MIP', 'mini-pont' => 'MIP', 'rideau' => 'RID',
            'rideau métalliques' => 'RID', 'rideau metallique' => 'RID', 'rideau métallique' => 'RID',
            'niveleur' => 'NIV', 'portail' => 'PAU', 'portail motorisé' => 'PMO',
            'portail motorise' => 'PMO', 'portail manuel' => 'PMA', 'portail coulissant' => 'PCO',
            'protection' => 'PRO', 'portillon' => 'POR', 'table elevatrice' => 'TEL',
            'tourniquet' => 'TOU', 'issue de secours' => 'BPO', 'bloc roue' => 'BLR',
            'sas' => 'SAS', 'plaque de quai' => 'PLQ',
        ];

        $typeLibelle = strtolower(trim($typeLibelle));
        
        if (isset($typeCodeMap[$typeLibelle])) {
            return $typeCodeMap[$typeLibelle];
        }
        
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
}