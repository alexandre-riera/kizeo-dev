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
    private const BATCH_SIZE = 5; // Traitement par lots de 5
    private const MAX_EXECUTION_TIME = 50; // 50 secondes max pour éviter timeout
    
    private HttpClientInterface $client;
    private FormRepository $formRepository;

    public function __construct(HttpClientInterface $client, FormRepository $formRepository)
    {
        $this->client = $client;
        $this->formRepository = $formRepository;
    }

    /**
     * Route principale pour traiter les formulaires de maintenance par lots
     */
    #[Route('/api/forms/process/maintenance/batch', name: 'app_process_maintenance_batch', methods: ['GET'])]
    public function processMaintenanceBatch(
        EntityManagerInterface $entityManager, 
        CacheInterface $cache,
        Request $request
    ): JsonResponse {
        $startTime = time();
        $processedCount = 0;
        $totalProcessed = 0;
        
        try {
            // Récupérer l'offset depuis la requête (pour la pagination)
            $offset = $request->query->get('offset', 0);
            $agencyFilter = $request->query->get('agency', null); // Filtrer par agence si nécessaire
            
            // Récupérer les formulaires de maintenance non lus par lots
            $unreadForms = $this->getUnreadMaintenanceFormsBatch($cache, self::BATCH_SIZE, $offset, $agencyFilter);
            
            if (empty($unreadForms)) {
                return new JsonResponse([
                    'status' => 'completed',
                    'message' => 'Aucun formulaire non lu trouvé',
                    'processed' => 0,
                    'total_processed' => $totalProcessed
                ]);
            }

            // Traitement des formulaires
            foreach ($unreadForms as $formData) {
                // Vérifier le temps d'exécution
                if ((time() - $startTime) >= self::MAX_EXECUTION_TIME) {
                    break;
                }

                try {
                    // Récupérer les données détaillées du formulaire
                    $formDetails = $this->getFormDetails($formData['form_id'], $formData['data_id']);
                    
                    if ($formDetails && isset($formDetails['fields'])) {
                        // AJOUT : Enregistrer les photos
                        $this->uploadPicturesInDatabase($formDetails, $entityManager);
                        
                        // Traiter et enregistrer les équipements
                        $this->processFormEquipments($formDetails['fields'], $entityManager);
                        
                        // Marquer le formulaire comme lu
                        $this->markFormAsRead($formData['form_id'], $formData['data_id']);
                        
                        $processedCount++;
                        $totalProcessed++;
                    }
                } catch (\Exception $e) {
                    error_log("Erreur lors du traitement du formulaire {$formData['form_id']}/{$formData['data_id']}: " . $e->getMessage());
                    continue;
                }
            }

            // Déterminer s'il y a encore des formulaires à traiter
            $hasMore = count($unreadForms) === self::BATCH_SIZE;
            $nextOffset = $offset + self::BATCH_SIZE;

            return new JsonResponse([
                'status' => $hasMore ? 'continue' : 'completed',
                'processed' => $processedCount,
                'total_processed' => $totalProcessed,
                'next_offset' => $hasMore ? $nextOffset : null,
                'execution_time' => (time() - $startTime),
                'next_url' => $hasMore ? $this->generateUrl('app_process_maintenance_batch', ['offset' => $nextOffset]) : null
            ]);

        } catch (\Exception $e) {
            error_log('Erreur lors du traitement par lots: ' . $e->getMessage());
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors du traitement',
                'processed' => $processedCount
            ], 500);
        }
    }

    /**
     * Route pour traiter automatiquement tous les formulaires par lots successifs
     * AMÉLIORATION : Augmentation du timeout et de la limite par lot
     */
    #[Route('/api/forms/process/maintenance/auto', name: 'app_process_maintenance_auto', methods: ['GET'])]
    public function processMaintenanceAuto(EntityManagerInterface $entityManager, CacheInterface $cache): JsonResponse
    {
        // AMÉLIORATION : Augmenter la limite de temps et la taille des lots
        set_time_limit(900); // 15 minutes
        ini_set('memory_limit', '1024M'); // 1GB de RAM
        
        $stats = [
            'total_processed' => 0,
            'batches_processed' => 0,
            'errors' => 0,
            'start_time' => time()
        ];

        $offset = 0;
        $hasMore = true;
        $batchSize = 10; // AMÉLIORATION : Augmenter la taille du lot

        while ($hasMore && (time() - $stats['start_time']) < 840) { // 14 minutes max (garder 1 min de marge)
            try {
                $unreadForms = $this->getUnreadMaintenanceFormsBatch($cache, $batchSize, $offset);
                
                if (empty($unreadForms)) {
                    $hasMore = false;
                    break;
                }

                $batchProcessed = 0;
                foreach ($unreadForms as $formData) {
                    try {
                        $formDetails = $this->getFormDetails($formData['form_id'], $formData['data_id']);
                        
                        if ($formDetails && isset($formDetails['fields'])) {
                            // AJOUT : Enregistrer les photos
                            $this->uploadPicturesInDatabase($formDetails, $entityManager);
                            
                            $this->processFormEquipments($formDetails['fields'], $entityManager);
                            $this->markFormAsRead($formData['form_id'], $formData['data_id']);
                            $batchProcessed++;
                        }
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        error_log("Erreur formulaire {$formData['form_id']}: " . $e->getMessage());
                    }
                }

                $stats['total_processed'] += $batchProcessed;
                $stats['batches_processed']++;
                $offset += $batchSize;
                
                // Si on a traité moins que la taille du lot, on a terminé
                if (count($unreadForms) < $batchSize) {
                    $hasMore = false;
                }

                // AMÉLIORATION : Réduire la pause pour traiter plus rapidement
                usleep(50000); // 0.05 seconde

            } catch (\Exception $e) {
                $stats['errors']++;
                error_log('Erreur dans le lot: ' . $e->getMessage());
                break;
            }
        }

        return new JsonResponse([
            'status' => 'completed',
            'stats' => $stats,
            'execution_time' => (time() - $stats['start_time']),
            'remaining_check' => $hasMore ? 'Il pourrait y avoir encore des formulaires à traiter' : 'Tous les formulaires disponibles ont été traités'
        ]);
    }

    /**
     * NOUVEAU : Route pour continuer le traitement là où on s'est arrêté
     */
    #[Route('/api/forms/process/maintenance/continue', name: 'app_process_maintenance_continue', methods: ['GET'])]
    public function processMaintenanceContinue(
        EntityManagerInterface $entityManager, 
        CacheInterface $cache,
        Request $request
    ): JsonResponse {
        $startOffset = $request->query->get('offset', 0);
        $maxBatches = $request->query->get('max_batches', 20); // Limite de lots à traiter
        
        $totalProcessed = 0;
        $batchesProcessed = 0;
        $errors = 0;
        $currentOffset = $startOffset;
        
        for ($i = 0; $i < $maxBatches; $i++) {
            $response = $this->processMaintenanceBatch($entityManager, $cache, new Request(['offset' => $currentOffset]));
            $data = json_decode($response->getContent(), true);
            
            if ($data['status'] === 'completed' && $data['processed'] === 0) {
                break; // Plus de formulaires à traiter
            }
            
            $totalProcessed += $data['processed'] ?? 0;
            $batchesProcessed++;
            
            if ($data['status'] === 'completed' || !isset($data['next_offset'])) {
                break;
            }
            
            $currentOffset = $data['next_offset'];
            
            // Petite pause entre les lots
            usleep(100000); // 0.1 seconde
        }
        
        return new JsonResponse([
            'status' => 'completed',
            'start_offset' => $startOffset,
            'final_offset' => $currentOffset,
            'batches_processed' => $batchesProcessed,
            'total_processed' => $totalProcessed,
            'continue_url' => $currentOffset > $startOffset ? 
                $this->generateUrl('app_process_maintenance_continue', ['offset' => $currentOffset]) : null
        ]);
    }

    /**
     * AJOUT : Méthode pour enregistrer les photos (adaptée de FormRepository)
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
            error_log("Erreur lors de l'enregistrement des photos: " . $e->getMessage());
        }
    }

    /**
     * AJOUT : Méthode pour sauvegarder les photos d'un équipement
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
            
            // Photos des équipements au contrat
            if (isset($contractEquipment['photo_etiquette_somafi']['value'])) {
                $equipement->setPhotoEtiquetteSomafi($contractEquipment['photo_etiquette_somafi']['value']);
            }
            $equipement->setPhotoPlaque($contractEquipment['photo_plaque']['value'] ?? '');
            $equipement->setPhotoChoc($contractEquipment['photo_choc']['value'] ?? '');
            
            // Toutes les autres photos du contrat
            $this->setContractEquipmentPhotos($equipement, $contractEquipment);
            
        } elseif ($offContractEquipment) {
            // Équipement HORS CONTRAT
            $equipement->setRaisonSocialeVisite($formData['fields']['contrat_de_maintenance']['value'][0]['equipement']['path'] ?? '');
            $equipement->setPhotoCompteRendu($offContractEquipment['photo3']['value'] ?? '');
        }

        $entityManager->persist($equipement);
    }

    /**
     * AJOUT : Méthode pour définir toutes les photos des équipements au contrat
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
        
        // Cas spéciaux avec des noms différents
        if (isset($contractEquipment['photo_panneau_intermediaire_i']['value'])) {
            $equipement->setPhotoPanneauIntermediaireI($contractEquipment['photo_panneau_intermediaire_i']['value']);
        }
        if (isset($contractEquipment['photo_panneau_bas_inter_ext']['value'])) {
            $equipement->setPhotoPanneauBasInterExt($contractEquipment['photo_panneau_bas_inter_ext']['value']);
        }
        if (isset($contractEquipment['photo_lame_basse_int_ext']['value'])) {
            $equipement->setPhotoLameBasseIntExt($contractEquipment['photo_lame_basse_int_ext']['value']);
        }
        if (isset($contractEquipment['photo_lame_intermediaire_int_']['value'])) {
            $equipement->setPhotoLameIntermediaireInt($contractEquipment['photo_lame_intermediaire_int_']['value']);
        }
        if (isset($contractEquipment['photo_environnement_equipemen1']['value'])) {
            $equipement->setPhotoEnvironnementEquipement1($contractEquipment['photo_environnement_equipemen1']['value']);
        }
        if (isset($contractEquipment['photo_marquage_au_sol_']['value'])) {
            $equipement->setPhotoMarquageAuSol2($contractEquipment['photo_marquage_au_sol_']['value']);
        }
        if (isset($contractEquipment['photo2']['value'])) {
            $equipement->setPhoto2($contractEquipment['photo2']['value']);
        }
    }

    /**
     * Récupère les formulaires de maintenance non lus par lots avec filtrage par agence CORRIGÉ
     */
    private function getUnreadMaintenanceFormsBatch(CacheInterface $cache, int $limit, int $offset = 0, ?string $agencyFilter = null): array
    {
        // Récupérer les formulaires de maintenance depuis le cache
        $maintenanceForms = $cache->get('maintenance_forms_list', function($item) {
            $item->expiresAfter(3600); // 1 heure
            
            $response = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
            ]);
            
            $content = $response->toArray();
            return array_filter($content['forms'], function($form) {
                return $form['class'] == "MAINTENANCE";
            });
        });

        $unreadForms = [];
        $processedCount = 0;

        foreach ($maintenanceForms as $form) {
            if (count($unreadForms) >= $limit) {
                break;
            }

            try {
                // Récupérer les données non lues pour ce formulaire
                $response = $this->client->request('GET', 
                    "https://forms.kizeo.com/rest/v3/forms/{$form['id']}/data/unread/bienlu/" . ($limit * 2), [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]);

                $result = $response->toArray();
                
                foreach ($result['data'] as $formData) {
                    // CORRECTION : Vérifier l'offset avant de traiter
                    if ($processedCount < $offset) {
                        $processedCount++;
                        continue;
                    }
                    
                    if (count($unreadForms) >= $limit) {
                        break 2; // Sortir des deux boucles
                    }

                    // CORRECTION : Filtrer par agence AVANT d'ajouter à la liste
                    if ($agencyFilter) {
                        $details = $this->getFormDetails($formData['_form_id'], $formData['_id']);
                        if (!$details || !isset($details['fields']['code_agence']['value']) || 
                            $details['fields']['code_agence']['value'] !== $agencyFilter) {
                            $processedCount++;
                            continue; // Passer au formulaire suivant si ce n'est pas la bonne agence
                        }
                    }

                    $unreadForms[] = [
                        'form_id' => $formData['_form_id'],
                        'data_id' => $formData['_id']
                    ];
                    $processedCount++;
                }
            } catch (\Exception $e) {
                error_log("Erreur lors de la récupération des formulaires non lus pour {$form['id']}: " . $e->getMessage());
                continue;
            }
        }

        return $unreadForms;
    }

    /**
     * NOUVELLE MÉTHODE : Filtrage plus efficace par agence
     */
    private function getUnreadMaintenanceFormsByAgency(string $agency, int $limit, int $offset = 0): array
    {
        $unreadForms = [];
        $processedCount = 0;
        $foundCount = 0;

        try {
            // Récupérer tous les formulaires de maintenance
            $response = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
            ]);
            
            $content = $response->toArray();
            $maintenanceForms = array_filter($content['forms'], function($form) {
                return $form['class'] == "MAINTENANCE";
            });

            foreach ($maintenanceForms as $form) {
                if ($foundCount >= $limit) {
                    break;
                }

                try {
                    // Récupérer les données non lues pour ce formulaire
                    $response = $this->client->request('GET', 
                        "https://forms.kizeo.com/rest/v3/forms/{$form['id']}/data/unread/bienlu/50", [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]);

                    $result = $response->toArray();
                    
                    foreach ($result['data'] as $formData) {
                        if ($foundCount >= $limit) {
                            break 2;
                        }

                        // Récupérer les détails pour vérifier l'agence
                        $details = $this->getFormDetails($formData['_form_id'], $formData['_id']);
                        
                        if ($details && isset($details['fields']['code_agence']['value']) && 
                            $details['fields']['code_agence']['value'] === $agency) {
                            
                            // Vérifier l'offset
                            if ($processedCount >= $offset) {
                                $unreadForms[] = [
                                    'form_id' => $formData['_form_id'],
                                    'data_id' => $formData['_id']
                                ];
                                $foundCount++;
                            }
                            $processedCount++;
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Erreur lors de la récupération pour {$form['id']}: " . $e->getMessage());
                    continue;
                }
            }

        } catch (\Exception $e) {
            error_log("Erreur générale dans getUnreadMaintenanceFormsByAgency: " . $e->getMessage());
        }

        return $unreadForms;
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
            ]);

            $result = $response->toArray();
            return $result['data'] ?? null;
        } catch (\Exception $e) {
            error_log("Erreur lors de la récupération des détails du formulaire {$formId}/{$dataId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Traite et enregistre les équipements d'un formulaire
     */
    private function processFormEquipments(array $fields, EntityManagerInterface $entityManager): void
    {
        if (!isset($fields['code_agence']['value'])) {
            return;
        }

        $entityClass = $this->getEntityClassByAgency($fields['code_agence']['value']);
        if (!$entityClass) {
            return;
        }

        // Traitement des équipements AU CONTRAT
        if (isset($fields['contrat_de_maintenance']['value'])) {
            $this->processContractEquipments($fields, $entityClass, $entityManager);
        }

        // Traitement des équipements HORS CONTRAT
        if (isset($fields['tableau2']['value'])) {
            $this->processOffContractEquipments($fields, $entityClass, $entityManager);
        }
    }

    /**
     * Marque un formulaire comme lu
     */
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
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Erreur lors du marquage comme lu du formulaire {$formId}/{$dataId}: " . $e->getMessage());
        }
    }

    /**
     * Route pour obtenir le statut du traitement
     */
    #[Route('/api/forms/status/maintenance', name: 'app_maintenance_status', methods: ['GET'])]
    public function getMaintenanceStatus(CacheInterface $cache): JsonResponse
    {
        try {
            $stats = [];
            
            // Compter les formulaires non lus par agence
            $maintenanceForms = $cache->get('maintenance_forms_list', function($item) {
                $item->expiresAfter(300); // 5 minutes pour le statut
                
                $response = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]);
                
                $content = $response->toArray();
                return array_filter($content['forms'], function($form) {
                    return $form['class'] == "MAINTENANCE";
                });
            });

            $totalUnread = 0;
            foreach ($maintenanceForms as $form) {
                try {
                    $response = $this->client->request('GET', 
                        "https://forms.kizeo.com/rest/v3/forms/{$form['id']}/data/unread/bienlu/100", [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]);

                    $result = $response->toArray();
                    $count = count($result['data']);
                    $totalUnread += $count;
                    
                    $stats['forms'][] = [
                        'form_id' => $form['id'],
                        'form_name' => $form['name'],
                        'unread_count' => $count
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }

            return new JsonResponse([
                'total_unread' => $totalUnread,
                'estimated_batches' => ceil($totalUnread / self::BATCH_SIZE),
                'batch_size' => self::BATCH_SIZE,
                'forms_details' => $stats['forms'] ?? []
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la récupération du statut'], 500);
        }
    }

    // Reprendre les méthodes de traitement des équipements du WebhookController précédent
    private function processContractEquipments(array $fields, string $entityClass, EntityManagerInterface $entityManager): void
    {
        // Code identique au WebhookController
        foreach ($fields['contrat_de_maintenance']['value'] as $additionalEquipment) {
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
        }
        
        $entityManager->flush();
    }

    private function processOffContractEquipments(array $fields, string $entityClass, EntityManagerInterface $entityManager): void
    {
        foreach ($fields['tableau2']['value'] as $equipementsHorsContrat) {
            $equipement = new $entityClass();
            
            // Données communes
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
            
            // Dimensions (optionnelles)
            $equipement->setLargeur($equipementsHorsContrat['largeur']['value'] ?? '');
            $equipement->setHauteur($equipementsHorsContrat['hauteur']['value'] ?? '');
            
            $equipement->setPlaqueSignaletique($equipementsHorsContrat['plaque_signaletique1']['value']);
            $equipement->setEtat($equipementsHorsContrat['etat1']['value']);
            
            // Statut de maintenance pour équipements hors contrat
            $equipement->setStatutDeMaintenance($this->getOffContractMaintenanceStatus($equipementsHorsContrat['etat1']['value']));
            
            // Type de visite basé sur le premier équipement au contrat
            $equipement->setVisite($this->getDefaultVisitType($fields));
            
            $equipement->setEnMaintenance(false);
            $equipement->setIsArchive(false);
            
            $entityManager->persist($equipement);
        }
        
        $entityManager->flush();
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
     * Détermination du type de visite basé sur le chemin de l'équipement
     */
    private function getVisitType(string $equipmentPath): string
    {
        if (str_contains($equipmentPath, 'CE1')) return 'CE1';
        if (str_contains($equipmentPath, 'CE2')) return 'CE2';
        if (str_contains($equipmentPath, 'CE3')) return 'CE3';
        if (str_contains($equipmentPath, 'CE4')) return 'CE4';
        if (str_contains($equipmentPath, 'CEA')) return 'CEA';
        
        return 'CE1'; // Valeur par défaut
    }

    /**
     * Type de visite par défaut pour équipements hors contrat
     */
    private function getDefaultVisitType(array $fields): string
    {
        if (!empty($fields['contrat_de_maintenance']['value'])) {
            return $this->getVisitType($fields['contrat_de_maintenance']['value'][0]['equipement']['path']);
        }
        return 'CE1';
    }

    /**
     * Statut de maintenance pour équipements au contrat
     */
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

    /**
     * Statut de maintenance pour équipements hors contrat
     */
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

    /**
     * Obtient le code du type d'équipement à partir du libellé
     */
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

    /**
     * STRATÉGIE DE TRAITEMENT COMPLÈTE
     * 
     * 1. Utilisez d'abord cette route pour connaître le nombre total :
     */
    #[Route('/api/forms/count/maintenance/unread', name: 'app_count_maintenance_unread', methods: ['GET'])]
    public function countUnreadMaintenanceForms(CacheInterface $cache): JsonResponse
    {
        try {
            $maintenanceForms = $cache->get('maintenance_forms_list', function($item) {
                $item->expiresAfter(300); // 5 minutes
                
                $response = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]);
                
                $content = $response->toArray();
                return array_filter($content['forms'], function($form) {
                    return $form['class'] == "MAINTENANCE";
                });
            });

            $totalUnread = 0;
            $formDetails = [];
            
            foreach ($maintenanceForms as $form) {
                try {
                    $response = $this->client->request('GET', 
                        "https://forms.kizeo.com/rest/v3/forms/{$form['id']}/data/unread/bienlu/100", [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 10
                    ]);

                    $result = $response->toArray();
                    $count = count($result['data']);
                    $totalUnread += $count;
                    
                    if ($count > 0) {
                        $formDetails[] = [
                            'form_id' => $form['id'],
                            'form_name' => $form['name'],
                            'unread_count' => $count
                        ];
                    }
                } catch (\Exception $e) {
                    error_log("Erreur form {$form['id']}: " . $e->getMessage());
                    continue;
                }
            }

            $estimatedBatches = ceil($totalUnread / 10); // Avec lots de 10
            $estimatedTime = $estimatedBatches * 2; // 2 minutes par lot

            return new JsonResponse([
                'total_unread_forms' => $totalUnread,
                'forms_with_unread' => count($formDetails),
                'estimated_batches' => $estimatedBatches,
                'estimated_time_minutes' => $estimatedTime,
                'forms_details' => $formDetails,
                'recommendations' => [
                    'use_continue_route' => $totalUnread > 50,
                    'suggested_batch_size' => min(10, max(3, floor(50 / count($maintenanceForms)))),
                    'max_iterations_needed' => ceil($totalUnread / 10)
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors du comptage: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 2. Route pour traitement en boucle jusqu'à completion
     */
    #[Route('/api/forms/process/maintenance/complete', name: 'app_process_maintenance_complete', methods: ['GET'])]
    public function processMaintenanceComplete(
        EntityManagerInterface $entityManager, 
        CacheInterface $cache,
        Request $request
    ): JsonResponse {
        set_time_limit(0); // Pas de limite
        ini_set('memory_limit', '2048M'); // 2GB
        
        $maxIterations = $request->query->get('max_iterations', 50); // Limite de sécurité
        $batchSize = $request->query->get('batch_size', 5);
        
        $globalStats = [
            'total_processed' => 0,
            'total_errors' => 0,
            'iterations' => 0,
            'start_time' => time(),
            'batches_details' => []
        ];
        
        $offset = 0;
        $hasMore = true;
        $consecutiveEmptyBatches = 0;
        
        while ($hasMore && $globalStats['iterations'] < $maxIterations) {
            $iterationStart = time();
            
            try {
                // Traiter un lot
                $request = new Request(['offset' => $offset, 'batch_size' => $batchSize]);
                $response = $this->processMaintenanceBatch($entityManager, $cache, $request);
                $batchData = json_decode($response->getContent(), true);
                
                $processed = $batchData['processed'] ?? 0;
                $globalStats['total_processed'] += $processed;
                
                if ($processed === 0) {
                    $consecutiveEmptyBatches++;
                    if ($consecutiveEmptyBatches >= 3) {
                        $hasMore = false; // Arrêter si 3 lots vides consécutifs
                        break;
                    }
                } else {
                    $consecutiveEmptyBatches = 0;
                }
                
                $globalStats['batches_details'][] = [
                    'iteration' => $globalStats['iterations'] + 1,
                    'offset' => $offset,
                    'processed' => $processed,
                    'status' => $batchData['status'] ?? 'unknown',
                    'execution_time' => time() - $iterationStart
                ];
                
                // Avancer l'offset
                if (isset($batchData['next_offset'])) {
                    $offset = $batchData['next_offset'];
                } else {
                    $offset += $batchSize;
                }
                
                $globalStats['iterations']++;
                
                // Pause entre les itérations
                usleep(100000); // 0.1 seconde
                
            } catch (\Exception $e) {
                $globalStats['total_errors']++;
                error_log("Erreur iteration {$globalStats['iterations']}: " . $e->getMessage());
                
                // Avancer même en cas d'erreur
                $offset += $batchSize;
                $globalStats['iterations']++;
            }
            
            // Vérification de sécurité temps
            if ((time() - $globalStats['start_time']) > 1800) { // 30 minutes max
                break;
            }
        }
        
        $globalStats['end_time'] = time();
        $globalStats['total_execution_time'] = $globalStats['end_time'] - $globalStats['start_time'];
        
        return new JsonResponse([
            'status' => 'completed',
            'global_stats' => $globalStats,
            'summary' => [
                'total_forms_processed' => $globalStats['total_processed'],
                'total_iterations' => $globalStats['iterations'],
                'total_time_minutes' => round($globalStats['total_execution_time'] / 60, 2),
                'average_time_per_iteration' => $globalStats['iterations'] > 0 ? 
                    round($globalStats['total_execution_time'] / $globalStats['iterations'], 2) : 0,
                'stopped_reason' => $hasMore ? 'max_iterations_reached' : 'no_more_data'
            ]
        ]);
    }

    /**
     * 3. Route pour traitement par agence (plus efficace)
     */
    #[Route('/api/forms/process/maintenance/by-agency/{agency}', name: 'app_process_maintenance_by_agency', methods: ['GET'])]
    public function processMaintenanceByAgency(
        string $agency,
        EntityManagerInterface $entityManager, 
        CacheInterface $cache
    ): JsonResponse {
        set_time_limit(600); // 10 minutes par agence
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agency, $validAgencies)) {
            return new JsonResponse(['error' => 'Agence non valide'], 400);
        }
        
        $stats = [
            'agency' => $agency,
            'processed' => 0,
            'errors' => 0,
            'start_time' => time(),
            'details' => []
        ];
        
        try {
            $offset = 0;
            $hasMore = true;
            $batchSize = 5; // Réduire la taille pour un meilleur contrôle
            
            while ($hasMore && (time() - $stats['start_time']) < 540) { // 9 minutes max
                
                // CORRECTION : Utiliser la nouvelle méthode de filtrage
                $unreadForms = $this->getUnreadMaintenanceFormsByAgency($agency, $batchSize, $offset);
                
                if (empty($unreadForms)) {
                    $hasMore = false;
                    break;
                }
                
                $batchProcessed = 0;
                $batchErrors = 0;
                
                foreach ($unreadForms as $formData) {
                    try {
                        $formDetails = $this->getFormDetails($formData['form_id'], $formData['data_id']);
                        
                        if ($formDetails && isset($formDetails['fields'])) {
                            // Vérifier encore une fois l'agence (double sécurité)
                            if ($formDetails['fields']['code_agence']['value'] === $agency) {
                                // Enregistrer les photos
                                $this->uploadPicturesInDatabase($formDetails, $entityManager);
                                
                                // Enregistrer les équipements
                                $this->processFormEquipments($formDetails['fields'], $entityManager);
                                
                                // Marquer comme lu
                                $this->markFormAsRead($formData['form_id'], $formData['data_id']);
                                
                                $batchProcessed++;
                            } else {
                                error_log("ATTENTION: Formulaire {$formData['form_id']} de l'agence {$formDetails['fields']['code_agence']['value']} traité au lieu de $agency");
                            }
                        }
                    } catch (\Exception $e) {
                        $batchErrors++;
                        error_log("Erreur formulaire {$formData['form_id']}: " . $e->getMessage());
                    }
                }
                
                $stats['processed'] += $batchProcessed;
                $stats['errors'] += $batchErrors;
                $stats['details'][] = [
                    'offset' => $offset,
                    'batch_processed' => $batchProcessed,
                    'batch_errors' => $batchErrors,
                    'forms_found' => count($unreadForms)
                ];
                
                $offset += $batchSize;
                
                // Si on a traité moins que la taille du lot, on a probablement terminé
                if (count($unreadForms) < $batchSize) {
                    $hasMore = false;
                }
                
                usleep(100000); // 0.1 seconde entre les lots
            }
            
        } catch (\Exception $e) {
            $stats['errors']++;
            error_log("Erreur agence $agency: " . $e->getMessage());
        }
        
        $stats['execution_time'] = time() - $stats['start_time'];
        
        return new JsonResponse([
            'status' => 'completed',
            'agency_stats' => $stats
        ]);
    }
    /**
     * ROUTE AMÉLIORÉE pour traitement par agence avec gestion des timeouts
     */
    #[Route('/api/forms/process/maintenance/agency/{agency}/optimized', name: 'app_process_maintenance_agency_optimized', methods: ['GET'])]
    public function processMaintenanceAgencyOptimized(
        string $agency,
        EntityManagerInterface $entityManager, 
        CacheInterface $cache,
        Request $request
    ): JsonResponse {
        // Configuration pour traitement long
        set_time_limit(1800); // 30 minutes
        ini_set('memory_limit', '2048M'); // 2GB
        ini_set('max_execution_time', 1800);
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agency, $validAgencies)) {
            return new JsonResponse(['error' => 'Agence non valide'], 400);
        }
        
        $startTime = time();
        $maxExecutionTime = 1500; // 25 minutes pour garder de la marge
        
        $stats = [
            'agency' => $agency,
            'processed_contract' => 0,
            'processed_off_contract' => 0,
            'total_processed' => 0,
            'errors' => 0,
            'start_time' => $startTime,
            'details' => [],
            'error_details' => []
        ];
        
        try {
            // ÉTAPE 1 : Compter tous les formulaires de cette agence
            error_log("🔍 [AGENCY $agency] Début du comptage des formulaires...");
            $totalFormsCount = $this->countFormsForAgency($agency);
            error_log("📊 [AGENCY $agency] Total formulaires trouvés: $totalFormsCount");
            
            $stats['total_forms_found'] = $totalFormsCount;
            
            // ÉTAPE 2 : Traitement par petits lots pour éviter le timeout
            $batchSize = 3; // RÉDUIT pour éviter les timeouts
            $processedForms = 0;
            $hasMore = true;
            
            while ($hasMore && (time() - $startTime) < $maxExecutionTime) {
                $batchStartTime = time();
                error_log("⚙️ [AGENCY $agency] Traitement lot $processedForms à " . ($processedForms + $batchSize));
                
                // Récupérer un petit lot de formulaires
                $formsBatch = $this->getFormsBatchForAgency($agency, $batchSize, $processedForms);
                
                if (empty($formsBatch)) {
                    error_log("✅ [AGENCY $agency] Aucun formulaire supplémentaire trouvé - Fin du traitement");
                    $hasMore = false;
                    break;
                }
                
                $batchStats = [
                    'batch_number' => floor($processedForms / $batchSize) + 1,
                    'forms_in_batch' => count($formsBatch),
                    'processed_contract' => 0,
                    'processed_off_contract' => 0,
                    'errors' => 0
                ];
                
                // Traiter chaque formulaire du lot
                foreach ($formsBatch as $formData) {
                    $formStartTime = time();
                    
                    // Sécurité timeout
                    if ((time() - $startTime) >= $maxExecutionTime) {
                        error_log("⏰ [AGENCY $agency] TIMEOUT atteint - Arrêt du traitement");
                        $hasMore = false;
                        break;
                    }
                    
                    try {
                        error_log("📝 [AGENCY $agency] Traitement formulaire {$formData['form_id']}/{$formData['data_id']}");
                        
                        $formDetails = $this->getFormDetails($formData['form_id'], $formData['data_id']);
                        
                        if ($formDetails && isset($formDetails['fields'])) {
                            // Vérification agence
                            if ($formDetails['fields']['code_agence']['value'] === $agency) {
                                
                                // CORRECTION : Enregistrer les photos d'abord
                                $this->uploadPicturesInDatabase($formDetails, $entityManager);
                                
                                // CORRECTION : Traitement équipements AU CONTRAT et HORS CONTRAT
                                $equipmentResults = $this->processFormEquipmentsDetailed($formDetails['fields'], $entityManager);
                                
                                $batchStats['processed_contract'] += $equipmentResults['contract_equipment'];
                                $batchStats['processed_off_contract'] += $equipmentResults['off_contract_equipment'];
                                
                                // Marquer comme lu
                                $this->markFormAsRead($formData['form_id'], $formData['data_id']);
                                
                                $formTime = time() - $formStartTime;
                                error_log("✅ [AGENCY $agency] Formulaire traité en {$formTime}s - AU CONTRAT: {$equipmentResults['contract_equipment']}, HORS CONTRAT: {$equipmentResults['off_contract_equipment']}");
                                
                            } else {
                                error_log("❌ [AGENCY $agency] Agence incorrecte: {$formDetails['fields']['code_agence']['value']}");
                            }
                        }
                    } catch (\Exception $e) {
                        $batchStats['errors']++;
                        $stats['error_details'][] = [
                            'form_id' => $formData['form_id'],
                            'data_id' => $formData['data_id'],
                            'error' => $e->getMessage(),
                            'timestamp' => date('Y-m-d H:i:s')
                        ];
                        error_log("❌ [AGENCY $agency] Erreur formulaire {$formData['form_id']}: " . $e->getMessage());
                    }
                }
                
                $stats['processed_contract'] += $batchStats['processed_contract'];
                $stats['processed_off_contract'] += $batchStats['processed_off_contract'];
                $stats['total_processed'] += $batchStats['processed_contract'] + $batchStats['processed_off_contract'];
                $stats['errors'] += $batchStats['errors'];
                
                $batchTime = time() - $batchStartTime;
                $batchStats['execution_time'] = $batchTime;
                $stats['details'][] = $batchStats;
                
                error_log("📊 [AGENCY $agency] Lot terminé en {$batchTime}s - Total AU CONTRAT: {$stats['processed_contract']}, HORS CONTRAT: {$stats['processed_off_contract']}");
                
                $processedForms += count($formsBatch);
                
                // Si on a traité moins que la taille du lot, on a terminé
                if (count($formsBatch) < $batchSize) {
                    $hasMore = false;
                }
                
                // Pause entre les lots
                usleep(200000); // 0.2 seconde
            }
            
        } catch (\Exception $e) {
            $stats['errors']++;
            $stats['critical_error'] = $e->getMessage();
            error_log("💥 [AGENCY $agency] Erreur critique: " . $e->getMessage());
        }
        
        $stats['execution_time'] = time() - $startTime;
        $stats['execution_time_minutes'] = round($stats['execution_time'] / 60, 2);
        
        error_log("🎉 [AGENCY $agency] TRAITEMENT TERMINÉ:");
        error_log("   - Temps d'exécution: {$stats['execution_time_minutes']} minutes");
        error_log("   - Équipements AU CONTRAT: {$stats['processed_contract']}");
        error_log("   - Équipements HORS CONTRAT: {$stats['processed_off_contract']}");
        error_log("   - Total traité: {$stats['total_processed']}");
        error_log("   - Erreurs: {$stats['errors']}");
        
        return new JsonResponse([
            'status' => 'completed',
            'agency_stats' => $stats,
            'summary' => [
                'agency' => $agency,
                'contract_equipment_processed' => $stats['processed_contract'],
                'off_contract_equipment_processed' => $stats['processed_off_contract'],
                'total_equipment_processed' => $stats['total_processed'],
                'execution_time_minutes' => $stats['execution_time_minutes'],
                'errors_count' => $stats['errors']
            ]
        ]);
    }

    /**
     * NOUVELLE MÉTHODE : Comptage des formulaires pour une agence
     */
    private function countFormsForAgency(string $agency): int
    {
        $totalCount = 0;
        
        try {
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
                    $response = $this->client->request('GET', 
                        "https://forms.kizeo.com/rest/v3/forms/{$form['id']}/data/unread/bienlu/100", [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 20
                    ]);

                    $result = $response->toArray();
                    
                    foreach ($result['data'] as $formData) {
                        $details = $this->getFormDetails($formData['_form_id'], $formData['_id']);
                        if ($details && isset($details['fields']['code_agence']['value']) && 
                            $details['fields']['code_agence']['value'] === $agency) {
                            $totalCount++;
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Erreur comptage form {$form['id']}: " . $e->getMessage());
                    continue;
                }
            }
            
        } catch (\Exception $e) {
            error_log("Erreur comptage général: " . $e->getMessage());
        }
        
        return $totalCount;
    }

    /**
     * NOUVELLE MÉTHODE : Récupération par lots avec offset
     */
    private function getFormsBatchForAgency(string $agency, int $batchSize, int $offset): array
    {
        $forms = [];
        $currentOffset = 0;
        
        try {
            $response = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
            ]);
            
            $content = $response->toArray();
            $maintenanceForms = array_filter($content['forms'], function($form) {
                return $form['class'] == "MAINTENANCE";
            });

            foreach ($maintenanceForms as $form) {
                if (count($forms) >= $batchSize) {
                    break;
                }
                
                try {
                    $response = $this->client->request('GET', 
                        "https://forms.kizeo.com/rest/v3/forms/{$form['id']}/data/unread/bienlu/50", [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]);

                    $result = $response->toArray();
                    
                    foreach ($result['data'] as $formData) {
                        if (count($forms) >= $batchSize) {
                            break 2;
                        }
                        
                        $details = $this->getFormDetails($formData['_form_id'], $formData['_id']);
                        if ($details && isset($details['fields']['code_agence']['value']) && 
                            $details['fields']['code_agence']['value'] === $agency) {
                            
                            if ($currentOffset >= $offset) {
                                $forms[] = [
                                    'form_id' => $formData['_form_id'],
                                    'data_id' => $formData['_id']
                                ];
                            }
                            $currentOffset++;
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Erreur récupération batch form {$form['id']}: " . $e->getMessage());
                    continue;
                }
            }
            
        } catch (\Exception $e) {
            error_log("Erreur récupération batch général: " . $e->getMessage());
        }
        
        return $forms;
    }

    /**
     * NOUVELLE MÉTHODE : Traitement détaillé des équipements avec comptage
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
            $contractCount = $this->processContractEquipmentsWithCount($fields, $entityClass, $entityManager);
            $results['contract_equipment'] = $contractCount;
        }

        // Traitement des équipements HORS CONTRAT
        if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
            $offContractCount = $this->processOffContractEquipmentsWithCount($fields, $entityClass, $entityManager);
            $results['off_contract_equipment'] = $offContractCount;
        }
        
        return $results;
    }

    /**
     * NOUVELLE MÉTHODE : Traitement équipements au contrat avec comptage
     */
    private function processContractEquipmentsWithCount(array $fields, string $entityClass, EntityManagerInterface $entityManager): int
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
                error_log("Erreur équipement au contrat: " . $e->getMessage());
            }
        }
        
        $entityManager->flush();
        return $count;
    }

    /**
     * NOUVELLE MÉTHODE : Traitement équipements hors contrat avec comptage
     */
    private function processOffContractEquipmentsWithCount(array $fields, string $entityClass, EntityManagerInterface $entityManager): int
    {
        $count = 0;
        
        foreach ($fields['tableau2']['value'] as $equipementsHorsContrat) {
            try {
                $equipement = new $entityClass();
                
                // Données communes
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
                
                // Dimensions (optionnelles)
                $equipement->setLargeur($equipementsHorsContrat['largeur']['value'] ?? '');
                $equipement->setHauteur($equipementsHorsContrat['hauteur']['value'] ?? '');
                
                $equipement->setPlaqueSignaletique($equipementsHorsContrat['plaque_signaletique1']['value']);
                $equipement->setEtat($equipementsHorsContrat['etat1']['value']);
                
                // Statut de maintenance pour équipements hors contrat
                $equipement->setStatutDeMaintenance($this->getOffContractMaintenanceStatus($equipementsHorsContrat['etat1']['value']));
                
                // Type de visite basé sur le premier équipement au contrat
                $equipement->setVisite($this->getDefaultVisitType($fields));
                
                $equipement->setEnMaintenance(false);
                $equipement->setIsArchive(false);
                
                $entityManager->persist($equipement);
                $count++;
                
            } catch (\Exception $e) {
                error_log("Erreur équipement hors contrat: " . $e->getMessage());
            }
        }
        
        $entityManager->flush();
        return $count;
    }

    /**
     * Route de diagnostic pour identifier les équipements manquants
     */
    #[Route('/api/forms/diagnostic/agency/{agency}', name: 'app_diagnostic_agency', methods: ['GET'])]
    public function diagnosticAgency(string $agency, CacheInterface $cache): JsonResponse
    {
        $diagnostics = [
            'agency' => $agency,
            'forms_found' => 0,
            'total_contract_equipment' => 0,
            'total_off_contract_equipment' => 0,
            'forms_details' => [],
            'errors' => []
        ];
        
        try {
            $response = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
            ]);
            
            $content = $response->toArray();
            $maintenanceForms = array_filter($content['forms'], function($form) {
                return $form['class'] == "MAINTENANCE";
            });

            foreach ($maintenanceForms as $form) {
                try {
                    $response = $this->client->request('GET', 
                        "https://forms.kizeo.com/rest/v3/forms/{$form['id']}/data/unread/bienlu/100", [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]);

                    $result = $response->toArray();
                    
                    foreach ($result['data'] as $formData) {
                        $details = $this->getFormDetails($formData['_form_id'], $formData['_id']);
                        
                        if ($details && isset($details['fields']['code_agence']['value']) && 
                            $details['fields']['code_agence']['value'] === $agency) {
                            
                            $diagnostics['forms_found']++;
                            
                            $contractEquipment = 0;
                            $offContractEquipment = 0;
                            
                            // Compter équipements au contrat
                            if (isset($details['fields']['contrat_de_maintenance']['value'])) {
                                $contractEquipment = count($details['fields']['contrat_de_maintenance']['value']);
                            }
                            
                            // Compter équipements hors contrat
                            if (isset($details['fields']['tableau2']['value'])) {
                                $offContractEquipment = count($details['fields']['tableau2']['value']);
                            }
                            
                            $diagnostics['total_contract_equipment'] += $contractEquipment;
                            $diagnostics['total_off_contract_equipment'] += $offContractEquipment;
                            
                            $diagnostics['forms_details'][] = [
                                'form_id' => $formData['_form_id'],
                                'data_id' => $formData['_id'],
                                'client_name' => $details['fields']['nom_client']['value'] ?? 'N/A',
                                'contract_equipment_count' => $contractEquipment,
                                'off_contract_equipment_count' => $offContractEquipment,
                                'visit_date' => $details['fields']['date_et_heure1']['value'] ?? 'N/A'
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $diagnostics['errors'][] = [
                        'form_id' => $form['id'],
                        'error' => $e->getMessage()
                    ];
                    continue;
                }
            }
            
        } catch (\Exception $e) {
            $diagnostics['critical_error'] = $e->getMessage();
        }
        
        return new JsonResponse($diagnostics);
    }
}