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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SimplifiedMaintenanceController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Traitement automatique complet avec gestion par chunks
     */
    #[Route('/api/maintenance/process-auto-chunked/{agencyCode}', name: 'app_maintenance_process_auto_chunked', methods: ['GET'])]
    public function processMaintenanceAutoChunked(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration pour éviter les timeouts et problèmes mémoire
        ini_set('memory_limit', '1G');
        ini_set('max_execution_time', 600); // 10 minutes max
        set_time_limit(0);
        gc_enable();
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        $formId = $request->query->get('form_id', '1088761');
        $entryId = $request->query->get('entry_id');
        $chunkSize = (int) $request->query->get('chunk_size', 15);

        if (!$entryId) {
            return new JsonResponse([
                'error' => 'Paramètre entry_id requis',
                'example' => "/api/maintenance/process-auto-chunked/{$agencyCode}?entry_id=233668811&chunk_size=15"
            ], 400);
        }

        try {
            // 1. Analyser le formulaire pour déterminer la taille optimale des chunks
            $totalEquipments = $this->getTotalEquipments($formId, $entryId);
            
            if ($totalEquipments === 0) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucun équipement à traiter'
                ]);
            }

            // 2. Traitement automatique par lots
            $offset = 0;
            $totalProcessed = 0;
            $totalPhotos = 0;
            $totalErrors = 0;
            $startTime = time();
            $allResults = [];
            
            while ($offset < $totalEquipments) {
                try {
                    // Appel au traitement par chunk
                    $batchRequest = Request::create(
                        '/api/maintenance/process-chunk/' . $agencyCode,
                        'GET',
                        [
                            'form_id' => $formId,
                            'entry_id' => $entryId,
                            'chunk_size' => $chunkSize,
                            'offset' => $offset
                        ]
                    );
                    
                    $batchResponse = $this->processMaintenanceChunk($agencyCode, $entityManager, $batchRequest);
                    $batchData = json_decode($batchResponse->getContent(), true);
                    
                    if ($batchData['success']) {
                        $processed = $batchData['batch_info']['processed_in_this_batch'];
                        $photos = $batchData['batch_info']['photos_processed'] ?? 0;
                        $totalProcessed += $processed;
                        $totalPhotos += $photos;
                        
                        $allResults[] = [
                            'offset' => $offset,
                            'processed' => $processed,
                            'photos' => $photos,
                            'contract_equipments' => $batchData['batch_info']['contract_processed'],
                            'off_contract_equipments' => $batchData['batch_info']['off_contract_processed']
                        ];
                        
                        $offset += $chunkSize;
                    } else {
                        $totalErrors++;
                        error_log("Erreur batch offset {$offset}: " . ($batchData['error'] ?? 'Erreur inconnue'));
                        break;
                    }
                    
                    // Protection contre les boucles infinies
                    if ($offset > $totalEquipments * 2) {
                        error_log("Protection boucle infinie activée à offset {$offset}");
                        break;
                    }
                    
                } catch (\Exception $e) {
                    $totalErrors++;
                    error_log("Erreur traitement batch: " . $e->getMessage());
                    break;
                }
            }
            
            $processingTime = time() - $startTime;
            
            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'entry_id' => $entryId,
                'summary' => [
                    'total_equipments' => $totalEquipments,
                    'total_processed' => $totalProcessed,
                    'total_photos' => $totalPhotos,
                    'total_errors' => $totalErrors,
                    'processing_time_seconds' => $processingTime,
                    'chunk_size_used' => $chunkSize,
                    'batches_processed' => count($allResults)
                ],
                'batch_details' => $allResults,
                'status' => $offset >= $totalEquipments ? 'completed' : 'partial',
                'message' => $offset >= $totalEquipments ? 
                    "Traitement automatique terminé: {$totalProcessed}/{$totalEquipments} équipements et {$totalPhotos} sets de photos traités en {$processingTime}s" :
                    "Traitement partiel: {$totalProcessed}/{$totalEquipments} équipements et {$totalPhotos} sets de photos traités en {$processingTime}s"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Traitement d'un chunk spécifique avec photos
     */
    #[Route('/api/maintenance/process-chunk/{agencyCode}', name: 'app_maintenance_process_chunk', methods: ['GET'])]
    public function processMaintenanceChunk(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration optimisée pour un chunk
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 120);
        
        $formId = $request->query->get('form_id', '1088761');
        $entryId = $request->query->get('entry_id');
        $chunkSize = (int) $request->query->get('chunk_size', 15);
        $startOffset = (int) $request->query->get('offset', 0);

        if (!$entryId) {
            return new JsonResponse([
                'error' => 'Paramètre entry_id requis'
            ], 400);
        }

        try {
            // Récupérer les données du formulaire
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            
            if (!isset($detailData['data']['fields'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Formulaire sans données valides'
                ], 400);
            }

            $fields = $detailData['data']['fields'];
            
            // Vérifier que c'est la bonne agence
            if (isset($fields['code_agence']['value']) && $fields['code_agence']['value'] !== $agencyCode) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Agence ne correspond pas: ' . ($fields['code_agence']['value'] ?? 'N/A')
                ], 400);
            }

            // Préparer les équipements
            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
            $offContractEquipments = $fields['tableau2']['value'] ?? [];
            
            // Combiner tous les équipements avec leur type
            $allEquipments = [];
            
            foreach ($contractEquipments as $index => $equipment) {
                $allEquipments[] = [
                    'type' => 'contract',
                    'data' => $equipment,
                    'index' => $index
                ];
            }
            
            foreach ($offContractEquipments as $index => $equipment) {
                $allEquipments[] = [
                    'type' => 'off_contract',
                    'data' => $equipment,
                    'index' => $index
                ];
            }

            // Découper en chunks
            $chunk = array_slice($allEquipments, $startOffset, $chunkSize);
            
            if (empty($chunk)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Chunk vide - traitement terminé',
                    'batch_info' => [
                        'total_equipments' => count($allEquipments),
                        'processed_in_this_batch' => 0,
                        'contract_processed' => 0,
                        'off_contract_processed' => 0,
                        'photos_processed' => 0,
                        'is_complete' => true
                    ]
                ]);
            }

            // Déterminer la classe d'entité
            $entityClass = $this->getEntityClass($agencyCode);
            
            // Traiter le chunk
            $processedEquipments = 0;
            $contractProcessed = 0;
            $offContractProcessed = 0;
            $photosProcessed = 0;
            $errors = [];

            foreach ($chunk as $equipmentData) {
                try {
                    $equipement = new $entityClass();
                    $this->setCommonEquipmentData($equipement, $fields);
                    
                    if ($equipmentData['type'] === 'contract') {
                        $this->setContractEquipmentData($equipement, $equipmentData['data']);
                        $contractProcessed++;
                    } else {
                        $this->setOffContractEquipmentData($equipement, $equipmentData['data'], $fields, $entityClass, $entityManager);
                        $offContractProcessed++;
                    }
                    
                    // Traitement des photos
                    $photoCount = $this->processEquipmentPhotos($equipement, $equipmentData['data']);
                    $photosProcessed += $photoCount;
                    
                    $entityManager->persist($equipement);
                    $processedEquipments++;
                    
                    // Sauvegarder tous les 5 équipements pour éviter la surcharge
                    if ($processedEquipments % 5 === 0) {
                        $entityManager->flush();
                        $entityManager->clear();
                        gc_collect_cycles();
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'equipment_index' => $equipmentData['index'],
                        'type' => $equipmentData['type'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Sauvegarde finale
            $entityManager->flush();
            $entityManager->clear();

            $nextOffset = $startOffset + $chunkSize;
            $isComplete = $nextOffset >= count($allEquipments);
            
            // Marquer comme lu seulement si c'est le dernier chunk
            if ($isComplete) {
                $this->markFormAsRead($formId, $entryId);
            }

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'entry_id' => $entryId,
                'client_name' => $fields['nom_du_client']['value'] ?? '',
                'batch_info' => [
                    'total_equipments' => count($allEquipments),
                    'processed_in_this_batch' => $processedEquipments,
                    'contract_processed' => $contractProcessed,
                    'off_contract_processed' => $offContractProcessed,
                    'photos_processed' => $photosProcessed,
                    'start_offset' => $startOffset,
                    'chunk_size' => $chunkSize,
                    'next_offset' => $nextOffset,
                    'is_complete' => $isComplete
                ],
                'errors' => $errors,
                'next_call' => $isComplete ? null : 
                    "/api/maintenance/process-chunk/{$agencyCode}?form_id={$formId}&entry_id={$entryId}&offset={$nextOffset}&chunk_size={$chunkSize}",
                'message' => $isComplete ? 
                    "Traitement terminé: {$processedEquipments} équipements et {$photosProcessed} sets de photos" :
                    "Chunk traité: {$processedEquipments} équipements et {$photosProcessed} sets de photos. Appeler next_call pour continuer"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Traitement automatique de toutes les soumissions d'un formulaire par agence
     */
    #[Route('/api/maintenance/process-auto-chunked-all/{agencyCode}', name: 'app_maintenance_process_auto_chunked_all', methods: ['GET'])]
    public function processMaintenanceAutoChunkedAll(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration pour traitement de masse
        ini_set('memory_limit', '2G');
        ini_set('max_execution_time', 1800); // 30 minutes max
        set_time_limit(0);
        gc_enable();
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        $formId = $request->query->get('form_id', '1088761');
        $chunkSize = (int) $request->query->get('chunk_size', 15);
        $mode = $request->query->get('mode', 'all_unprocessed'); // all_unprocessed ou all

        try {
            // 1. Récupérer toutes les soumissions du formulaire
            $submissions = $this->getFormSubmissions($formId, $mode);
            
            if (empty($submissions)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucune soumission à traiter pour le formulaire ' . $formId,
                    'agency' => $agencyCode,
                    'total_submissions' => 0
                ]);
            }

            // 2. Filtrer par agence et compter les équipements
            $relevantSubmissions = [];
            $totalEquipments = 0;

            foreach ($submissions as $submission) {
                $submissionData = $this->getSubmissionData($formId, $submission['_id']);
                
                if (!$submissionData || !isset($submissionData['data']['fields'])) {
                    continue;
                }

                $fields = $submissionData['data']['fields'];
                
                // Vérifier si c'est la bonne agence
                if (isset($fields['code_agence']['value']) && $fields['code_agence']['value'] === $agencyCode) {
                    $contractCount = count($fields['contrat_de_maintenance']['value'] ?? []);
                    $offContractCount = count($fields['tableau2']['value'] ?? []);
                    $equipmentCount = $contractCount + $offContractCount;
                    
                    $relevantSubmissions[] = [
                        'entry_id' => $submission['_id'],
                        'equipment_count' => $equipmentCount,
                        'client_name' => $fields['nom_du_client']['value'] ?? 'N/A',
                        'date' => $submission['_record_date'] ?? 'N/A'
                    ];
                    
                    $totalEquipments += $equipmentCount;
                }
            }

            if (empty($relevantSubmissions)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucune soumission trouvée pour l\'agence ' . $agencyCode,
                    'agency' => $agencyCode,
                    'total_submissions_checked' => count($submissions),
                    'relevant_submissions' => 0
                ]);
            }

            // 3. Traitement de toutes les soumissions
            $startTime = time();
            $allResults = [];
            $totalProcessed = 0;
            $totalPhotos = 0;
            $totalErrors = 0;
            $submissionsProcessed = 0;
            $submissionsWithErrors = 0;

            foreach ($relevantSubmissions as $index => $submissionInfo) {
                try {
                    error_log("Traitement soumission " . ($index + 1) . "/" . count($relevantSubmissions) . 
                             " - Entry ID: " . $submissionInfo['entry_id'] . 
                             " - Client: " . $submissionInfo['client_name'] . 
                             " - Équipements: " . $submissionInfo['equipment_count']);

                    // Appel au traitement automatique par chunks pour cette soumission
                    $submissionRequest = Request::create(
                        '/api/maintenance/process-auto-chunked/' . $agencyCode,
                        'GET',
                        [
                            'form_id' => $formId,
                            'entry_id' => $submissionInfo['entry_id'],
                            'chunk_size' => $chunkSize
                        ]
                    );
                    
                    $submissionResponse = $this->processMaintenanceAutoChunked($agencyCode, $entityManager, $submissionRequest);
                    $submissionData = json_decode($submissionResponse->getContent(), true);
                    
                    if ($submissionData['success']) {
                        $processed = $submissionData['summary']['total_processed'];
                        $photos = $submissionData['summary']['total_photos'];
                        $errors = $submissionData['summary']['total_errors'];
                        
                        $totalProcessed += $processed;
                        $totalPhotos += $photos;
                        $totalErrors += $errors;
                        $submissionsProcessed++;
                        
                        if ($errors > 0) {
                            $submissionsWithErrors++;
                        }
                        
                        $allResults[] = [
                            'submission_index' => $index + 1,
                            'entry_id' => $submissionInfo['entry_id'],
                            'client_name' => $submissionInfo['client_name'],
                            'equipment_count' => $submissionInfo['equipment_count'],
                            'processed' => $processed,
                            'photos' => $photos,
                            'errors' => $errors,
                            'processing_time' => $submissionData['summary']['processing_time_seconds'],
                            'status' => $submissionData['status']
                        ];
                    } else {
                        $submissionsWithErrors++;
                        $allResults[] = [
                            'submission_index' => $index + 1,
                            'entry_id' => $submissionInfo['entry_id'],
                            'client_name' => $submissionInfo['client_name'],
                            'error' => $submissionData['error'] ?? 'Erreur inconnue',
                            'status' => 'failed'
                        ];
                        error_log("Erreur traitement soumission " . $submissionInfo['entry_id'] . ": " . 
                                 ($submissionData['error'] ?? 'Erreur inconnue'));
                    }

                    // Nettoyage mémoire après chaque soumission
                    gc_collect_cycles();
                    
                    // Pause courte pour éviter la surcharge
                    usleep(100000); // 0.1 seconde

                } catch (\Exception $e) {
                    $submissionsWithErrors++;
                    $allResults[] = [
                        'submission_index' => $index + 1,
                        'entry_id' => $submissionInfo['entry_id'],
                        'client_name' => $submissionInfo['client_name'],
                        'error' => $e->getMessage(),
                        'status' => 'exception'
                    ];
                    error_log("Exception traitement soumission " . $submissionInfo['entry_id'] . ": " . $e->getMessage());
                }
            }
            
            $processingTime = time() - $startTime;
            
            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'mode' => $mode,
                'global_summary' => [
                    'total_submissions_found' => count($submissions),
                    'relevant_submissions' => count($relevantSubmissions),
                    'submissions_processed' => $submissionsProcessed,
                    'submissions_with_errors' => $submissionsWithErrors,
                    'total_equipments_expected' => $totalEquipments,
                    'total_equipments_processed' => $totalProcessed,
                    'total_photos_processed' => $totalPhotos,
                    'total_errors' => $totalErrors,
                    'total_processing_time_seconds' => $processingTime,
                    'chunk_size_used' => $chunkSize,
                    'success_rate' => round(($submissionsProcessed / count($relevantSubmissions)) * 100, 2)
                ],
                'submission_details' => $allResults,
                'status' => $submissionsWithErrors === 0 ? 'completed_success' : 'completed_with_errors',
                'message' => sprintf(
                    "Traitement terminé pour l'agence %s: %d/%d soumissions traitées, %d/%d équipements traités, %d photos en %ds",
                    $agencyCode,
                    $submissionsProcessed,
                    count($relevantSubmissions),
                    $totalProcessed,
                    $totalEquipments,
                    $totalPhotos,
                    $processingTime
                )
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'agency' => $agencyCode
            ], 500);
        }
    }

    /**
     * Récupérer toutes les soumissions d'un formulaire
     */
    private function getFormSubmissions(string $formId, string $mode = 'all_unprocessed'): array
    {
        try {
            $endpoint = $mode === 'all_unprocessed' 
                ? 'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/unread'
                : 'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced';

            if ($mode === 'all_unprocessed') {
                // Pour les non lus, simple GET
                $response = $this->client->request('GET', $endpoint, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 60
                ]);
            } else {
                // Pour toutes les données, utiliser POST avec filtre
                $response = $this->client->request('POST', $endpoint, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'filters' => [],
                        'order_by' => '_record_date',
                        'order' => 'desc',
                        'limit' => 1000 // Limite pour éviter les timeouts
                    ]),
                    'timeout' => 60
                ]);
            }

            $data = $response->toArray();
            return $data['data'] ?? [];

        } catch (\Exception $e) {
            error_log("Erreur getFormSubmissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les données détaillées d'une soumission
     */
    private function getSubmissionData(string $formId, string $entryId): ?array
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 30
                ]
            );

            return $response->toArray();

        } catch (\Exception $e) {
            error_log("Erreur getSubmissionData pour {$entryId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Méthodes utilitaires simplifiées
     */
    
    private function getTotalEquipments(string $formId, string $entryId): int
    {
        try {
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            $fields = $detailData['data']['fields'] ?? [];
            
            $contractCount = count($fields['contrat_de_maintenance']['value'] ?? []);
            $offContractCount = count($fields['tableau2']['value'] ?? []);
            
            return $contractCount + $offContractCount;
            
        } catch (\Exception $e) {
            error_log("Erreur getTotalEquipments: " . $e->getMessage());
            return 0;
        }
    }
    
    private function getEntityClass(string $agencyCode): string
    {
        $entityClasses = [
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
        
        return $entityClasses[$agencyCode] ?? EquipementS140::class;
    }

    private function setCommonEquipmentData($equipement, array $fields): void
    {
        $equipement->setCodeAgence($fields['code_agence']['value'] ?? '');
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_du_client']['value'] ?? '');
        $equipement->setTrigrammeTech($fields['technicien']['value'] ?? '');
        $equipement->setDateEnregistrement($fields['date_et_heure']['value'] ?? '');
        $equipement->setEtatDesLieuxFait(false);
        $equipement->setIsArchive(false);
    }

    private function setContractEquipmentData($equipement, array $equipmentContrat): void
    {
        // Extraction du type de visite depuis le path
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        // Données de base
        $numeroEquipement = $equipmentContrat['equipement']['value'] ?? 'AUTO_' . uniqid();
        $equipement->setNumeroEquipement($numeroEquipement);
        
        $libelle = $equipmentContrat['reference7']['value'] ?? 'Équipement';
        $equipement->setLibelleEquipement($libelle);
        
        $miseEnService = $equipmentContrat['reference2']['value'] ?? '';
        $equipement->setMiseEnService($miseEnService);
        
        $numeroSerie = $equipmentContrat['reference6']['value'] ?? '';
        $equipement->setNumeroDeSerie($numeroSerie);
        
        $marque = $equipmentContrat['reference5']['value'] ?? '';
        $equipement->setMarque($marque);
        
        $hauteur = $equipmentContrat['reference1']['value'] ?? '';
        $equipement->setHauteur($hauteur);
        
        $largeur = $equipmentContrat['reference3']['value'] ?? '';
        $equipement->setLargeur($largeur);
        
        $localisation = $equipmentContrat['localisation_site_client']['value'] ?? '';
        $equipement->setRepereSiteClient($localisation);
        
        $modeFonctionnement = $equipmentContrat['mode_fonctionnement_2']['value'] ?? '';
        $equipement->setModeFonctionnement($modeFonctionnement);
        
        $plaqueSignaletique = $equipmentContrat['plaque_signaletique']['value'] ?? '';
        $equipement->setPlaqueSignaletique($plaqueSignaletique);
        
        $etat = $equipmentContrat['etat']['value'] ?? '';
        $equipement->setEtat($etat);
    }

    private function setOffContractEquipmentData($equipement, array $equipmentHorsContrat, array $fields, string $entityClass, EntityManagerInterface $entityManager): void
    {
        // Détermination du type de visite depuis les équipements sous contrat
        $visite = "CE1"; // Valeur par défaut
        if (!empty($fields['contrat_de_maintenance']['value'])) {
            $firstContractEquipment = $fields['contrat_de_maintenance']['value'][0];
            $visite = $this->extractVisitTypeFromPath($firstContractEquipment['equipement']['path'] ?? '');
        }
        $equipement->setVisite($visite);
        
        // Attribution automatique du numéro d'équipement
        $typeLibelle = strtolower($equipmentHorsContrat['nature']['value'] ?? '');
        $typeCode = $this->getTypeCodeFromLibelle($typeLibelle);
        
        $idClient = $fields['id_client_']['value'] ?? '';
        $nouveauNumero = $this->getNextEquipmentNumberFromDatabase($typeCode, $idClient, $entityClass, $entityManager);
        
        $numeroFormate = $typeCode . str_pad($nouveauNumero, 2, '0', STR_PAD_LEFT);
        $equipement->setNumeroEquipement($numeroFormate);
        
        // Autres données
        $equipement->setLibelleEquipement($equipmentHorsContrat['nature']['value'] ?? '');
        $equipement->setRepereSiteClient($equipmentHorsContrat['localisation']['value'] ?? '');
        $equipement->setEtat($equipmentHorsContrat['etat2']['value'] ?? '');
        
        // Données par défaut pour hors contrat
        $equipement->setMiseEnService('');
        $equipement->setNumeroDeSerie('');
        $equipement->setMarque('');
        $equipement->setHauteur('');
        $equipement->setLargeur('');
        $equipement->setModeFonctionnement('');
        $equipement->setPlaqueSignaletique('');
    }

    private function processEquipmentPhotos($equipement, array $equipmentData): int
    {
        $photoCount = 0;
        
        // Photos de déformation
        if (!empty($equipmentData['photo_deformation_plaque']['value'])) {
            $equipement->setPhotoDeformationPlaque($equipmentData['photo_deformation_plaque']['value']);
            $photoCount++;
        }
        
        if (!empty($equipmentData['photo_deformation_structure']['value'])) {
            $equipement->setPhotoDeformationStructure($equipmentData['photo_deformation_structure']['value']);
            $photoCount++;
        }
        
        if (!empty($equipmentData['photo_deformation_chassis']['value'])) {
            $equipement->setPhotoDeformationChassis($equipmentData['photo_deformation_chassis']['value']);
            $photoCount++;
        }
        
        // Photos techniques
        if (!empty($equipmentData['photo_moteur']['value'])) {
            $equipement->setPhotoMoteur($equipmentData['photo_moteur']['value']);
            $photoCount++;
        }
        
        if (!empty($equipmentData['photo_coffret_de_commande']['value'])) {
            $equipement->setPhotoCoffretDeCommande($equipmentData['photo_coffret_de_commande']['value']);
            $photoCount++;
        }
        
        if (!empty($equipmentData['photo_carte']['value'])) {
            $equipement->setPhotoCarte($equipmentData['photo_carte']['value']);
            $photoCount++;
        }
        
        // Photos de chocs
        if (!empty($equipmentData['photo_choc']['value'])) {
            $equipement->setPhotoChoc($equipmentData['photo_choc']['value']);
            $photoCount++;
        }
        
        if (!empty($equipmentData['photo_choc_tablier']['value'])) {
            $equipement->setPhotoChocTablier($equipmentData['photo_choc_tablier']['value']);
            $photoCount++;
        }
        
        return $photoCount;
    }

    private function extractVisitTypeFromPath(string $path): string
    {
        if (str_contains($path, 'CE1')) return 'CE1';
        if (str_contains($path, 'CE2')) return 'CE2';
        if (str_contains($path, 'CE3')) return 'CE3';
        if (str_contains($path, 'CE4')) return 'CE4';
        if (str_contains($path, 'CEA')) return 'CEA';
        
        return 'CE1'; // Valeur par défaut
    }

    private function getTypeCodeFromLibelle(string $libelle): string
    {
        $typeMapping = [
            'sectionnelle' => 'SEC',
            'enroulable' => 'ENR',
            'battante' => 'BAT',
            'coulissante' => 'COU',
            'basculante' => 'BAS',
            'rideau' => 'RID',
            'barriere' => 'BAR',
            'borne' => 'BOR'
        ];
        
        foreach ($typeMapping as $keyword => $code) {
            if (str_contains($libelle, $keyword)) {
                return $code;
            }
        }
        
        return 'EQP'; // Code par défaut
    }

    private function getNextEquipmentNumberFromDatabase(string $typeCode, string $idClient, string $entityClass, EntityManagerInterface $entityManager): int
    {
        try {
            $repository = $entityManager->getRepository($entityClass);
            $queryBuilder = $repository->createQueryBuilder('e');
            
            $result = $queryBuilder
                ->select('MAX(CAST(SUBSTRING(e.numeroEquipement, ' . (strlen($typeCode) + 1) . ') AS INTEGER))')
                ->where('e.idContact = :idClient')
                ->andWhere('e.numeroEquipement LIKE :typeCode')
                ->setParameter('idClient', $idClient)
                ->setParameter('typeCode', $typeCode . '%')
                ->getQuery()
                ->getSingleScalarResult();
            
            return ($result !== null) ? $result + 1 : 1;
            
        } catch (\Exception $e) {
            error_log("Erreur getNextEquipmentNumber: " . $e->getMessage());
            return 1;
        }
    }

    private function markFormAsRead(string $formId, string $entryId): void
    {
        try {
            $this->client->request(
                'PUT',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/markasread/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );
        } catch (\Exception $e) {
            error_log("Erreur markFormAsRead: " . $e->getMessage());
        }
    }
}