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
     * Traitement automatique de toutes les soumissions d'un formulaire par agence (VERSION CORRIGÉE)
     */
    #[Route('/api/maintenance/process-auto-chunked-all/{agencyCode}', name: 'app_maintenance_process_auto_chunked_all', methods: ['GET'])]
    public function processMaintenanceAutoChunkedAll(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration pour traitement de masse avec gestion mémoire améliorée
        ini_set('memory_limit', '1G'); // Réduction pour éviter l'épuisement
        ini_set('max_execution_time', 900); // 15 minutes max
        set_time_limit(0);
        gc_enable();
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        $formId = $request->query->get('form_id', '1088761');
        $chunkSize = (int) $request->query->get('chunk_size', 10); // Réduction pour éviter les problèmes mémoire
        $mode = $request->query->get('mode', 'all_unprocessed');

        try {
            error_log("=== DEBUT TRAITEMENT AGENCE {$agencyCode} - FORM {$formId} ===");
            
            // 1. Récupérer toutes les soumissions du formulaire
            $submissions = $this->getFormSubmissions($formId, $mode);
            
            if (empty($submissions)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucune soumission à traiter pour le formulaire ' . $formId,
                    'agency' => $agencyCode,
                    'total_submissions' => 0,
                    'mode' => $mode
                ]);
            }

            error_log("Soumissions récupérées: " . count($submissions));

            // 2. Filtrer par agence et compter les équipements (traitement par lots pour économiser la mémoire)
            $relevantSubmissions = [];
            $totalEquipments = 0;
            $processedCount = 0;

            foreach ($submissions as $index => $submission) {
                try {
                    $entryId = $submission['_id'];
                    $submissionData = $this->getSubmissionData($formId, $entryId);
                    
                    if (!$submissionData || !isset($submissionData['data']['fields'])) {
                        error_log("Soumission {$entryId} : pas de données valides");
                        continue;
                    }

                    $fields = $submissionData['data']['fields'];
                    
                    // Vérifier si c'est la bonne agence
                    $submissionAgency = $fields['code_agence']['value'] ?? $fields['id_agence']['value'] ?? null;
                    
                    if ($submissionAgency === $agencyCode) {
                        $contractCount = count($fields['contrat_de_maintenance']['value'] ?? []);
                        $offContractCount = count($fields['tableau2']['value'] ?? $fields['hors_contrat']['value'] ?? []);
                        $equipmentCount = $contractCount + $offContractCount;
                        
                        $relevantSubmissions[] = [
                            'entry_id' => $entryId,
                            'equipment_count' => $equipmentCount,
                            'client_name' => $fields['nom_du_client']['value'] ?? $fields['nom_client']['value'] ?? 'N/A',
                            'date' => $submission['_record_date'] ?? 'N/A'
                        ];
                        
                        $totalEquipments += $equipmentCount;
                        
                        error_log("Soumission retenue: {$entryId} - Client: " . ($fields['nom_du_client']['value'] ?? 'N/A') . " - Équipements: {$equipmentCount}");
                    }
                    
                    $processedCount++;
                    
                    // Nettoyage mémoire périodique
                    if ($processedCount % 10 === 0) {
                        gc_collect_cycles();
                        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
                        error_log("Progression: {$processedCount}/" . count($submissions) . " - Mémoire: {$memoryUsage}MB");
                        
                        // Protection contre l'épuisement mémoire
                        if ($memoryUsage > 800) {
                            error_log("ATTENTION: Utilisation mémoire élevée ({$memoryUsage}MB), arrêt du préfiltrage");
                            break;
                        }
                    }
                    
                } catch (\Exception $e) {
                    error_log("Erreur traitement soumission {$index}: " . $e->getMessage());
                    continue;
                }
            }

            // Libérer la mémoire des soumissions non pertinentes
            unset($submissions);
            gc_collect_cycles();

            if (empty($relevantSubmissions)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucune soumission trouvée pour l\'agence ' . $agencyCode,
                    'agency' => $agencyCode,
                    'total_submissions_checked' => $processedCount,
                    'relevant_submissions' => 0
                ]);
            }

            error_log("Soumissions pertinentes pour {$agencyCode}: " . count($relevantSubmissions) . " (total équipements: {$totalEquipments})");

            // 3. Traitement de toutes les soumissions pertinentes
            $startTime = time();
            $allResults = [];
            $totalProcessed = 0;
            $totalPhotos = 0;
            $totalErrors = 0;
            $submissionsProcessed = 0;
            $submissionsWithErrors = 0;

            foreach ($relevantSubmissions as $index => $submissionInfo) {
                try {
                    $currentMemory = memory_get_usage(true) / 1024 / 1024;
                    error_log("Traitement soumission " . ($index + 1) . "/" . count($relevantSubmissions) . 
                             " - Entry ID: " . $submissionInfo['entry_id'] . 
                             " - Client: " . $submissionInfo['client_name'] . 
                             " - Équipements: " . $submissionInfo['equipment_count'] .
                             " - Mémoire: {$currentMemory}MB");

                    // Protection mémoire avant traitement
                    if ($currentMemory > 900) {
                        error_log("ARRÊT: Limite mémoire atteinte ({$currentMemory}MB)");
                        break;
                    }

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

                    // Nettoyage mémoire intensif après chaque soumission
                    $entityManager->clear();
                    gc_collect_cycles();
                    
                    // Pause pour éviter la surcharge
                    usleep(200000); // 0.2 seconde

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
                    
                    // Nettoyage d'urgence en cas d'erreur
                    $entityManager->clear();
                    gc_collect_cycles();
                }
            }
            
            $processingTime = time() - $startTime;
            $finalMemory = memory_get_usage(true) / 1024 / 1024;
            
            error_log("=== FIN TRAITEMENT {$agencyCode} - Temps: {$processingTime}s - Mémoire finale: {$finalMemory}MB ===");
            
            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'mode' => $mode,
                'global_summary' => [
                    'relevant_submissions' => count($relevantSubmissions),
                    'submissions_processed' => $submissionsProcessed,
                    'submissions_with_errors' => $submissionsWithErrors,
                    'total_equipments_expected' => $totalEquipments,
                    'total_equipments_processed' => $totalProcessed,
                    'total_photos_processed' => $totalPhotos,
                    'total_errors' => $totalErrors,
                    'total_processing_time_seconds' => $processingTime,
                    'chunk_size_used' => $chunkSize,
                    'success_rate' => count($relevantSubmissions) > 0 ? round(($submissionsProcessed / count($relevantSubmissions)) * 100, 2) : 0,
                    'memory_usage_mb' => $finalMemory
                ],
                'submission_details' => $allResults,
                'status' => $submissionsWithErrors === 0 ? 'completed_success' : 'completed_with_errors',
                'message' => sprintf(
                    "Traitement terminé pour l'agence %s: %d/%d soumissions traitées, %d/%d équipements traités, %d photos en %ds (Mémoire: %.1fMB)",
                    $agencyCode,
                    $submissionsProcessed,
                    count($relevantSubmissions),
                    $totalProcessed,
                    $totalEquipments,
                    $totalPhotos,
                    $processingTime,
                    $finalMemory
                )
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'agency' => $agencyCode,
                'memory_usage_mb' => memory_get_usage(true) / 1024 / 1024
            ], 500);
        }
    }

    /**
     * Récupérer toutes les soumissions d'un formulaire
     */
    private function getFormSubmissions(string $formId, string $mode = 'all_unprocessed'): array
    {
        try {
            if ($mode === 'all_unprocessed') {
                // Pour les non lus, utiliser l'endpoint unread avec action par défaut
                $response = $this->client->request('GET', 
                    'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/unread/default/1000?includeupdated', 
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 60
                    ]
                );
            } else {
                // Pour toutes les données, utiliser POST avec filtre vide
                $response = $this->client->request('POST', 
                    'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced', 
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            'Content-Type' => 'application/json',
                        ],
                        'body' => json_encode([
                            'filters' => [],
                            'order_by' => '_record_date',
                            'order' => 'desc',
                            'limit' => 1000
                        ]),
                        'timeout' => 60
                    ]
                );
            }

            $data = $response->toArray();
            
            // Log pour debugging
            error_log("API Response for form {$formId} (mode: {$mode}): " . json_encode([
                'status_code' => $response->getStatusCode(),
                'has_data' => isset($data['data']),
                'data_count' => isset($data['data']) ? count($data['data']) : 0,
                'response_keys' => array_keys($data)
            ]));
            
            return $data['data'] ?? [];

        } catch (\Exception $e) {
            error_log("Erreur getFormSubmissions pour form {$formId}: " . $e->getMessage());
            
            // Fallback: essayer l'endpoint simple data/all
            try {
                $response = $this->client->request('GET', 
                    'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/all', 
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 60
                    ]
                );
                
                $data = $response->toArray();
                error_log("Fallback API Response for form {$formId}: " . json_encode([
                    'status_code' => $response->getStatusCode(),
                    'has_data' => isset($data['data']),
                    'data_count' => isset($data['data']) ? count($data['data']) : 0
                ]));
                
                return $data['data'] ?? [];
                
            } catch (\Exception $fallbackError) {
                error_log("Erreur fallback getFormSubmissions: " . $fallbackError->getMessage());
                return [];
            }
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
     * Marquer des formulaires comme lus (API corrigée)
     */
    private function markFormAsRead(string $formId, string $entryId): void
    {
        try {
            // Utiliser la bonne API markasreadbyaction avec action par défaut
            $this->client->request(
                'POST',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/markasreadbyaction/default',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'data_ids' => [$entryId]
                    ]),
                    'timeout' => 30
                ]
            );
            
            error_log("Formulaire marqué comme lu: form_id={$formId}, entry_id={$entryId}");
            
        } catch (\Exception $e) {
            error_log("Erreur markFormAsRead: " . $e->getMessage());
        }
    }

    /**
     * Traitement par micro-lots pour éviter l'épuisement mémoire
     */
    #[Route('/api/maintenance/process-micro-batch/{agencyCode}', name: 'app_maintenance_process_micro_batch', methods: ['GET'])]
    public function processMaintenanceMicroBatch(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration ultra-conservative
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 600);
        set_time_limit(0);
        gc_enable();
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        $formId = $request->query->get('form_id', '1052966');
        $batchSize = (int) $request->query->get('batch_size', 5); // Traiter 5 soumissions à la fois
        $startIndex = (int) $request->query->get('start_index', 0);
        $mode = $request->query->get('mode', 'all');

        try {
            error_log("=== MICRO-BATCH {$agencyCode} - Form: {$formId} - Start: {$startIndex} ===");
            
            // 1. Récupérer les soumissions par petits groupes
            $allSubmissions = $this->getFormSubmissions($formId, $mode);
            
            if (empty($allSubmissions)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucune soumission disponible',
                    'agency' => $agencyCode,
                    'total_submissions' => 0
                ]);
            }

            // 2. Découper en micro-lot
            $batchSubmissions = array_slice($allSubmissions, $startIndex, $batchSize);
            $totalSubmissions = count($allSubmissions);
            
            if (empty($batchSubmissions)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Fin du traitement - tous les micro-lots traités',
                    'agency' => $agencyCode,
                    'start_index' => $startIndex,
                    'total_submissions' => $totalSubmissions,
                    'is_complete' => true
                ]);
            }

            error_log("Micro-lot: " . count($batchSubmissions) . " soumissions ({$startIndex} à " . ($startIndex + count($batchSubmissions) - 1) . "/{$totalSubmissions})");

            // 3. Traiter uniquement ce micro-lot
            $results = [];
            $totalProcessed = 0;
            $totalEquipments = 0;
            $totalPhotos = 0;
            $errors = [];

            foreach ($batchSubmissions as $index => $submission) {
                $currentIndex = $startIndex + $index;
                $entryId = $submission['_id'];
                
                try {
                    $memoryBefore = memory_get_usage(true) / 1024 / 1024;
                    error_log("Traitement soumission {$currentIndex}: {$entryId} - Mémoire avant: {$memoryBefore}MB");

                    // Récupérer et vérifier les données
                    $submissionData = $this->getSubmissionData($formId, $entryId);
                    
                    if (!$submissionData || !isset($submissionData['data']['fields'])) {
                        error_log("Soumission {$entryId}: données invalides");
                        continue;
                    }

                    $fields = $submissionData['data']['fields'];
                    $submissionAgency = $fields['code_agence']['value'] ?? $fields['id_agence']['value'] ?? null;
                    
                    if ($submissionAgency !== $agencyCode) {
                        error_log("Soumission {$entryId}: agence {$submissionAgency} ≠ {$agencyCode}");
                        continue;
                    }

                    // Traitement de cette soumission
                    $submissionRequest = Request::create(
                        '/api/maintenance/process-auto-chunked/' . $agencyCode,
                        'GET',
                        [
                            'form_id' => $formId,
                            'entry_id' => $entryId,
                            'chunk_size' => 3 // Chunk ultra-petit
                        ]
                    );
                    
                    $submissionResponse = $this->processMaintenanceAutoChunked($agencyCode, $entityManager, $submissionRequest);
                    $submissionResult = json_decode($submissionResponse->getContent(), true);
                    
                    if ($submissionResult['success']) {
                        $processed = $submissionResult['summary']['total_processed'];
                        $photos = $submissionResult['summary']['total_photos'];
                        
                        $totalProcessed++;
                        $totalEquipments += $processed;
                        $totalPhotos += $photos;
                        
                        $results[] = [
                            'index' => $currentIndex,
                            'entry_id' => $entryId,
                            'client_name' => $fields['nom_du_client']['value'] ?? $fields['nom_client']['value'] ?? 'N/A',
                            'equipments_processed' => $processed,
                            'photos_processed' => $photos,
                            'status' => 'success'
                        ];
                        
                        error_log("Soumission {$entryId}: SUCCESS - {$processed} équipements, {$photos} photos");
                    } else {
                        $errors[] = [
                            'index' => $currentIndex,
                            'entry_id' => $entryId,
                            'error' => $submissionResult['error'] ?? 'Erreur inconnue'
                        ];
                        error_log("Soumission {$entryId}: ERROR - " . ($submissionResult['error'] ?? 'Erreur inconnue'));
                    }

                    // Nettoyage intensif après chaque soumission
                    $entityManager->clear();
                    gc_collect_cycles();
                    
                    $memoryAfter = memory_get_usage(true) / 1024 / 1024;
                    error_log("Soumission {$entryId}: terminée - Mémoire après: {$memoryAfter}MB");
                    
                    // Protection mémoire
                    if ($memoryAfter > 400) {
                        error_log("ATTENTION: Mémoire élevée ({$memoryAfter}MB), arrêt du micro-lot");
                        break;
                    }
                    
                    // Pause entre soumissions
                    usleep(300000); // 0.3 seconde

                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $currentIndex,
                        'entry_id' => $entryId,
                        'error' => $e->getMessage()
                    ];
                    error_log("Exception soumission {$entryId}: " . $e->getMessage());
                    
                    // Nettoyage d'urgence
                    $entityManager->clear();
                    gc_collect_cycles();
                }
            }

            // 4. Calculer les infos pour le prochain micro-lot
            $nextIndex = $startIndex + $batchSize;
            $isComplete = $nextIndex >= $totalSubmissions;
            $nextUrl = $isComplete ? null : 
                "/api/maintenance/process-micro-batch/{$agencyCode}?form_id={$formId}&start_index={$nextIndex}&batch_size={$batchSize}&mode={$mode}";

            $finalMemory = memory_get_usage(true) / 1024 / 1024;
            error_log("=== FIN MICRO-BATCH - Processed: {$totalProcessed}, Mémoire finale: {$finalMemory}MB ===");

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'micro_batch_info' => [
                    'start_index' => $startIndex,
                    'batch_size' => $batchSize,
                    'total_submissions' => $totalSubmissions,
                    'processed_in_batch' => $totalProcessed,
                    'total_equipments' => $totalEquipments,
                    'total_photos' => $totalPhotos,
                    'errors_count' => count($errors),
                    'is_complete' => $isComplete,
                    'next_index' => $nextIndex,
                    'progress_percent' => round(($nextIndex / $totalSubmissions) * 100, 1),
                    'memory_usage_mb' => $finalMemory
                ],
                'results' => $results,
                'errors' => $errors,
                'next_call' => $nextUrl,
                'message' => $isComplete ? 
                    "Micro-lots terminés: {$totalProcessed} soumissions, {$totalEquipments} équipements traités" :
                    "Micro-lot {$startIndex}-{$nextIndex} terminé: {$totalProcessed} soumissions, {$totalEquipments} équipements. Appeler next_call pour continuer"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'agency' => $agencyCode,
                'memory_usage_mb' => memory_get_usage(true) / 1024 / 1024
            ], 500);
        }
    }

    /**
     * Route de traitement automatique des micro-lots
     */
    #[Route('/api/maintenance/process-all-micro-batches/{agencyCode}', name: 'app_maintenance_process_all_micro_batches', methods: ['GET'])]
    public function processAllMicroBatches(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        $formId = $request->query->get('form_id', '1052966');
        $batchSize = (int) $request->query->get('batch_size', 3);
        $maxBatches = (int) $request->query->get('max_batches', 20); // Limite de sécurité
        
        $allResults = [];
        $currentIndex = 0;
        $batchCount = 0;
        $totalProcessed = 0;
        $totalEquipments = 0;
        $startTime = time();
        
        error_log("=== DEBUT TRAITEMENT AUTO MICRO-BATCHES {$agencyCode} ===");
        
        while ($batchCount < $maxBatches) {
            try {
                error_log("Lancement micro-batch {$batchCount} à partir de l'index {$currentIndex}");
                
                // Appel au micro-batch
                $batchRequest = Request::create(
                    '/api/maintenance/process-micro-batch/' . $agencyCode,
                    'GET',
                    [
                        'form_id' => $formId,
                        'start_index' => $currentIndex,
                        'batch_size' => $batchSize,
                        'mode' => 'all'
                    ]
                );
                
                $batchResponse = $this->processMaintenanceMicroBatch($agencyCode, $entityManager, $batchRequest);
                $batchData = json_decode($batchResponse->getContent(), true);
                
                if (!$batchData['success']) {
                    error_log("Erreur micro-batch {$batchCount}: " . ($batchData['error'] ?? 'Erreur inconnue'));
                    break;
                }
                
                $batchInfo = $batchData['micro_batch_info'];
                $totalProcessed += $batchInfo['processed_in_batch'];
                $totalEquipments += $batchInfo['total_equipments'];
                
                $allResults[] = [
                    'batch_number' => $batchCount,
                    'start_index' => $currentIndex,
                    'processed' => $batchInfo['processed_in_batch'],
                    'equipments' => $batchInfo['total_equipments'],
                    'photos' => $batchInfo['total_photos'],
                    'errors' => $batchInfo['errors_count'],
                    'memory_mb' => $batchInfo['memory_usage_mb']
                ];
                
                error_log("Micro-batch {$batchCount} terminé: {$batchInfo['processed_in_batch']} soumissions, {$batchInfo['total_equipments']} équipements");
                
                if ($batchInfo['is_complete']) {
                    error_log("Tous les micro-lots terminés après {$batchCount} batches");
                    break;
                }
                
                $currentIndex = $batchInfo['next_index'];
                $batchCount++;
                
                // Pause entre micro-lots
                sleep(1);
                
            } catch (\Exception $e) {
                error_log("Exception micro-batch {$batchCount}: " . $e->getMessage());
                break;
            }
        }
        
        $totalTime = time() - $startTime;
        error_log("=== FIN TRAITEMENT AUTO MICRO-BATCHES - {$totalProcessed} soumissions en {$totalTime}s ===");
        
        return new JsonResponse([
            'success' => true,
            'agency' => $agencyCode,
            'form_id' => $formId,
            'final_summary' => [
                'total_batches_processed' => $batchCount,
                'total_submissions_processed' => $totalProcessed,
                'total_equipments_created' => $totalEquipments,
                'total_processing_time_seconds' => $totalTime,
                'batch_size_used' => $batchSize,
                'completed' => $batchCount < $maxBatches
            ],
            'batch_details' => $allResults,
            'message' => "Traitement par micro-lots terminé: {$totalProcessed} soumissions, {$totalEquipments} équipements en {$totalTime}s"
        ]);
    }
    #[Route('/api/maintenance/debug-kizeo/{formId}', name: 'app_maintenance_debug_kizeo', methods: ['GET'])]
    public function debugKizeoApi(string $formId, Request $request): JsonResponse
    {
        $mode = $request->query->get('mode', 'all_unprocessed');
        
        try {
            // Test 1: Vérifier l'existence du formulaire
            error_log("=== DEBUT DEBUG KIZEO FORM {$formId} ===");
            
            $formsResponse = $this->client->request('GET', 'https://forms.kizeo.com/rest/v3/forms', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'timeout' => 30
            ]);

            $allForms = $formsResponse->toArray();
            $targetForm = null;
            
            foreach ($allForms['forms'] as $form) {
                if ($form['id'] == $formId) {
                    $targetForm = $form;
                    break;
                }
            }

            if (!$targetForm) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Formulaire non trouvé',
                    'form_id' => $formId,
                    'available_forms' => array_map(function($f) {
                        return ['id' => $f['id'], 'name' => $f['name'], 'class' => $f['class']];
                    }, $allForms['forms'])
                ], 404);
            }

            // Test 2: Essayer différents endpoints pour récupérer les données
            $results = [];
            
            // Test endpoint unread
            try {
                $unreadResponse = $this->client->request('GET', 
                    'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/unread/default/100?includeupdated',
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 30
                    ]
                );
                $unreadData = $unreadResponse->toArray();
                $results['unread_endpoint'] = [
                    'success' => true,
                    'status_code' => $unreadResponse->getStatusCode(),
                    'data_count' => count($unreadData['data'] ?? []),
                    'sample_entry' => !empty($unreadData['data']) ? $unreadData['data'][0] : null
                ];
            } catch (\Exception $e) {
                $results['unread_endpoint'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }

            // Test endpoint data/all
            try {
                $allDataResponse = $this->client->request('GET', 
                    'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/all',
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 30
                    ]
                );
                $allData = $allDataResponse->toArray();
                $results['all_endpoint'] = [
                    'success' => true,
                    'status_code' => $allDataResponse->getStatusCode(),
                    'data_count' => count($allData['data'] ?? []),
                    'sample_entry' => !empty($allData['data']) ? $allData['data'][0] : null
                ];
            } catch (\Exception $e) {
                $results['all_endpoint'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }

            // Test endpoint advanced
            try {
                $advancedResponse = $this->client->request('POST', 
                    'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced',
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            'Content-Type' => 'application/json',
                        ],
                        'body' => json_encode([
                            'filters' => [],
                            'limit' => 10
                        ]),
                        'timeout' => 30
                    ]
                );
                $advancedData = $advancedResponse->toArray();
                $results['advanced_endpoint'] = [
                    'success' => true,
                    'status_code' => $advancedResponse->getStatusCode(),
                    'data_count' => count($advancedData['data'] ?? []),
                    'sample_entry' => !empty($advancedData['data']) ? $advancedData['data'][0] : null
                ];
            } catch (\Exception $e) {
                $results['advanced_endpoint'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }

            return new JsonResponse([
                'success' => true,
                'form_info' => [
                    'id' => $targetForm['id'],
                    'name' => $targetForm['name'],
                    'class' => $targetForm['class'],
                    'create_time' => $targetForm['create_time'] ?? null,
                    'update_time' => $targetForm['update_time'] ?? null
                ],
                'api_tests' => $results,
                'recommendations' => [
                    'Si unread_endpoint.data_count = 0' => 'Toutes les données ont déjà été marquées comme lues',
                    'Si all_endpoint.data_count > 0' => 'Le formulaire contient des données, utiliser mode=all',
                    'Si tous les endpoints retournent 0' => 'Le formulaire est vide ou les permissions sont insuffisantes'
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'form_id' => $formId
            ], 500);
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

}