<?php

namespace App\Controller;

use App\Entity\Form;
use GuzzleHttp\Client;
use App\Entity\Portail;
use App\Entity\ContactS10;
use App\Entity\ContactS50;
use App\Entity\Equipement;
use App\Entity\PortailAuto;
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
use Doctrine\ORM\EntityManager;
use App\Repository\FormRepository;
use App\Entity\PortailEnvironement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FormController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }
    /**
     * HomePage route to avoid Symfony loading default page
     * 
     */
    // #[Route('/', name: 'home', methods: ['GET'])]
    // public function home(){
    //     return new JsonResponse("L'application API KIZEO est lancée !", Response::HTTP_OK, [], true);
    // }

    /**
     * @return Form[]Function to get Grenoble clients list from BDD  
     */
    #[Route('/api/lists/get/grenoble/clients', name: 'app_api_get_lists_clients_grenoble', methods: ['GET'])]
    public function getListsClientsGrenoble(FormRepository $formRepository, SerializerInterface $serializer, EntityManagerInterface $entityManager): Response
    {
        // $formList  =  $formRepository->getAgencyListClientsFromKizeoByListId(409466);
        $clientList  =  $entityManager->getRepository(ContactS50::class)->findAll();
        $clientList = $serializer->serialize($clientList, 'json');
        $response = new Response($clientList);
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    /**
     * @return Form[]Function to get Grenoble clients equipments list from BDD  
     */
    #[Route('/api/lists/get/grenoble/equipements', name: 'app_api_get_lists_equipements_clients_grenoble', methods: ['GET'])]
    public function getListsEquipementsClientGrenoble(FormRepository $formRepository, SerializerInterface $serializer, EntityManagerInterface $entityManager): Response
    {
        // $formList  =  $formRepository->getAgencyListClientsFromKizeoByListId(409466);
        $equipementsClientList  =  $entityManager->getRepository(EquipementS50::class)->findAll();
        $equipementsClientList = $serializer->serialize($equipementsClientList, 'json');
        $response = new Response($equipementsClientList);
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @return Form[]Function to list all lists on Kizeo with getLists() function from FormRepository  
     */
    #[Route('/api/lists/get/equipements-contrat-38', name: 'app_api_get_lists_equipements_contrat_38', methods: ['GET'])]
    public function getListsEquipementsContrat38(FormRepository $formRepository, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse
    {
        $formList  =  $formRepository->getAgencyListEquipementsFromKizeoByListId(414025);

        return new JsonResponse("Formulaires parc client sur API KIZEO : " . count($formList), Response::HTTP_OK, [], true);
    }
    /**
     * @return Form[]Function to list all lists on Kizeo with getLists() function from FormRepository  
     */
    #[Route('/api/lists/get', name: 'app_api_get_lists', methods: ['GET'])]
    public function getLists(FormRepository $formRepository, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse
    {
        $formList  =  $formRepository->getLists();
        $jsonContactList = $serializer->serialize($formList, 'json');
        
        return new JsonResponse("Formulaires parc client sur API KIZEO : " . count($formList), Response::HTTP_OK, [], true);
    }
    /**
     * @return Form[]Function to list all forms on Kizeo with getForms() function from FormRepository  
     */
    #[Route('/api/forms/get', name: 'app_api_get_forms', methods: ['GET'])]
    public function getForms(FormRepository $formRepository, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse
    {
        $formList  =  $formRepository->getForms();
        $jsonContactList = $serializer->serialize($formList, 'json');
       
        // Fetch all contacts in database
        $allFormsInDatabase = $entityManager->getRepository(Form::class)->findAll();
        
        return new JsonResponse("Formulaires parc client sur API KIZEO : "  . " | Formulaires parc client en BDD : " . count($allFormsInDatabase) . "\n", Response::HTTP_OK, [], true);
    }
    /**
     * 
     * @return Form[] Returns an array of Formulaires with class PORTAILS 
     */
    #[Route('/api/forms/data', name: 'app_api_form_data', methods: ['GET'])]
    public function getFormsAdvanced(FormRepository $formRepository, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse
    {
        $formList  =  $formRepository->getFormsAdvancedPortails();
        $jsonContactList = $serializer->serialize($formList, 'json');
       
        // Fetch all contacts in database
        $allFormsInDatabase = $entityManager->getRepository(Form::class)->findAll();
        
        return new JsonResponse("Formulaires parc client sur API KIZEO : " . count($formList) . " | Formulaires parc client en BDD : " . count($allFormsInDatabase) . "\n", Response::HTTP_OK, [], true);
    }

    // ------------------------------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------------------------------

    /**
     * 
     * Save maintenance equipments in local database then call save equipments to KIZEO  --  FIRST CALL IN CRON TASK
     */
    #[Route('/api/forms/save/maintenance/equipments', name: 'app_api_form_save_maintenance_equipments', methods: ['GET'])]
    public function saveEquipementsInDatabase(FormRepository $formRepository, CacheInterface $cache) //: JsonResponse
    {
        $formRepository->saveEquipmentsInDatabase($cache);
        
        
        // return new JsonResponse("Les équipements de maintenance ont bien été sauvegardés ", Response::HTTP_OK, [], true);
        // return $this->redirectToRoute('app_api_form_save_maintenance_equipments'); // Sauvegarder 5 formulaires à la fois en BDD en BOUCLE A utiliser pour ne mettre à jour que la base de données
       
        // Commenter pour éviter de mettre les listes Kizeo à jour à chaque fois que l'on sauvegarde les équipements de maintenance
        return $this->redirectToRoute('app_api_form_update_lists_equipements_from_bdd'); 
    }

    // /**
    //  * -------------------------------------------------------------------------- SECOND CALL IN CRON TASK
    //  * This route is going to replace the route above to update equipments list on Kizeo Forms
    //  */
    // #[Route('/api/forms/update/lists/kizeo', name: 'app_api_form_update_lists_equipements_from_bdd', methods: ['GET','PUT'])]
    // public function updateKizeoFormsByEquipmentsListFromBdd(FormRepository $formRepository, CacheInterface $cache, EntityManagerInterface $entityManager): JsonResponse
    // {
    //     $formRepository->updateKizeoWithEquipmentsListFromBdd($entityManager, $formRepository, $cache);

    //     return new JsonResponse('La mise à jour sur KIZEO a été réalisée !', Response::HTTP_OK, [], true);
    //     // return $this->redirectToRoute('app_api_form_save_maintenance_equipments'); // A remettre pour faire tourner la boucle des 2 URL
    // }

    /**
     * -------------------------------------------------------------------------- SECOND CALL IN CRON TASK
     * This route is going to replace the route above to update equipments list on Kizeo Forms
     * VERSION AVEC CACHE REDIS ET GESTION D'ERREURS AMÉLIORÉE
     */
    #[Route('/api/forms/update/lists/kizeo', name: 'app_api_form_update_lists_equipements_from_bdd', methods: ['GET','PUT'])]
    public function updateKizeoFormsByEquipmentsListFromBdd(
        FormRepository $formRepository, 
        CacheInterface $cache, 
        EntityManagerInterface $entityManager
    ): JsonResponse {
        
        try {
            // Mesure du temps d'exécution
            $startTime = microtime(true);
            
            // Exécution de la mise à jour avec métriques
            $results = $formRepository->updateKizeoWithEquipmentsListFromBddWithMetrics(
                $entityManager, 
                $formRepository, 
                $cache
            );
            
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);
            
            // Comptage des succès et erreurs
            $successCount = $results['metrics']['success_count'];
            $errorCount = $results['metrics']['error_count'];
            $totalEntities = $results['metrics']['total_entities'];
            
            // Construction du message de réponse
            $message = sprintf(
                'Mise à jour Kizeo terminée - %d/%d entités mises à jour avec succès en %ds',
                $successCount,
                $totalEntities,
                $executionTime
            );
            
            // Ajout des détails d'erreur si il y en a
            if ($errorCount > 0) {
                $errors = array_filter($results['results'], fn($r) => $r['status'] === 'error');
                $errorDetails = array_map(fn($e) => $e['entite'] . ': ' . $e['error_message'], $errors);
                
                return new JsonResponse([
                    'message' => $message,
                    'status' => 'partial_success',
                    'metrics' => $results['metrics'],
                    'errors' => $errorDetails,
                    'details' => $results['results']
                ], Response::HTTP_OK);
            }
            
            // Succès total
            return new JsonResponse([
                'message' => $message,
                'status' => 'success',
                'metrics' => $results['metrics'],
                'details' => $results['results']
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            // Gestion des erreurs globales
            return new JsonResponse([
                'message' => 'Erreur lors de la mise à jour Kizeo',
                'status' => 'error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Route de diagnostic à ajouter temporairement dans FormController
     */
    #[Route('/api/forms/diagnose/sync', name: 'app_api_form_diagnose_sync', methods: ['GET'])]
    public function diagnoseSyncIssues(FormRepository $formRepository): JsonResponse
    {
        try {
            $diagnostic = $formRepository->diagnoseSyncIssues();
            
            return new JsonResponse([
                'status' => 'success',
                'diagnostic' => $diagnostic,
                'recommendations' => $this->generateRecommendations($diagnostic)
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Génère des recommandations basées sur le diagnostic
     */
    private function generateRecommendations(array $diagnostic): array
    {
        $recommendations = [];
        
        // Si aucune correspondance exacte
        $hasExactMatch = false;
        if (!empty($diagnostic['potential_matches'])) {
            foreach ($diagnostic['potential_matches'] as $match) {
                if (!empty($match['matches'])) {
                    foreach ($match['matches'] as $m) {
                        if ($m['type'] === 'exact_match') {
                            $hasExactMatch = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        if (!$hasExactMatch) {
            $recommendations[] = "Aucune correspondance exacte trouvée - vérifier le format des clés";
        }
        
        // Si les structures diffèrent
        $bddStructures = [];
        $kizeoStructures = [];
        
        if (!empty($diagnostic['bdd_samples'])) {
            foreach ($diagnostic['bdd_samples'] as $sample) {
                $bddStructures[] = $sample['key_structure'];
            }
        }
        
        if (!empty($diagnostic['kizeo_samples'])) {
            foreach ($diagnostic['kizeo_samples'] as $sample) {
                $kizeoStructures[] = $sample['key_structure'];
            }
        }
        
        $bddStructures = array_unique($bddStructures, SORT_REGULAR);
        $kizeoStructures = array_unique($kizeoStructures, SORT_REGULAR);
        
        if (count($bddStructures) > 1 || count($kizeoStructures) > 1) {
            $recommendations[] = "Structures de clés incohérentes détectées";
        }
        
        // Si problème d'encodage
        $encodingIssues = 0;
        $allSamples = array_merge($diagnostic['bdd_samples'] ?? [], $diagnostic['kizeo_samples'] ?? []);
        
        foreach ($allSamples as $sample) {
            if (isset($sample['key_structure']['encoding']) && $sample['key_structure']['encoding'] !== 'UTF-8') {
                $encodingIssues++;
            }
        }
            
        if ($encodingIssues > 0) {
            $recommendations[] = "Problèmes d'encodage détectés - normaliser les caractères";
        }
        
        return $recommendations;
    }

    /**
     * Route de test simplifiée sans dépendances complexes
     */
    #[Route('/api/forms/test/simple-sync', name: 'app_api_form_test_simple_sync', methods: ['GET'])]
    public function testSimpleSync(FormRepository $formRepository): JsonResponse
    {
        try {
            $testResult = $formRepository->testSyncLogicWithSample('App\\Entity\\EquipementS50', $formRepository);

            $analysis = 'OK - Pas de duplication prévue';
            if ($testResult['difference'] > 0) {
                $analysis = 'ATTENTION - Va ajouter ' . $testResult['difference'] . ' équipements en doublon';
            }
            
            return new JsonResponse([
                'status' => 'success',
                'test_result' => $testResult,
                'analysis' => $analysis
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Route pour appliquer la correction sur S50 uniquement
     */
    #[Route('/api/forms/fix/s50-sync', name: 'app_api_form_fix_s50_sync', methods: ['POST'])]
    public function fixS50Sync(
        FormRepository $formRepository,
        CacheInterface $cache,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        
        try {
            $entityClass = 'App\\Entity\\EquipementS50';
            $startTime = microtime(true);
            
            // Récupérer les équipements de la BDD
            $equipements = $entityManager->getRepository($entityClass)->findAll();
            $structuredEquipements = $formRepository->structureLikeKizeoEquipmentsList($equipements);
            
            // Récupérer l'ID de liste et vider le cache
            $idListeKizeo = $formRepository->getIdListeKizeoPourEntite($entityClass);
            $cache->delete('kizeo_equipments_s50');
            
            // Récupérer les données Kizeo
            $kizeoEquipments = $formRepository->getAgencyListEquipementsFromKizeoByListId($idListeKizeo);
            
            // Appliquer la nouvelle logique
            $updatedEquipments = $formRepository->compareAndSyncEquipments(
                $structuredEquipements,
                $kizeoEquipments,
                $idListeKizeo
            );
            
            $endTime = microtime(true);
            
            return new JsonResponse([
                'status' => 'success',
                'entity' => 'S50',
                'results' => [
                    'bdd_count' => count($structuredEquipements),
                    'kizeo_before' => count($kizeoEquipments),
                    'kizeo_after' => count($updatedEquipments),
                    'execution_time' => round($endTime - $startTime, 2)
                ],
                'message' => 'Logique corrigée appliquée avec succès pour S50'
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Route pour appliquer la correction SEULEMENT sur S50 - AJOUTER cette méthode
     */
    #[Route('/api/forms/fix/s50-only', name: 'app_api_form_fix_s50_only', methods: ['POST'])]
    public function fixS50Only(
        FormRepository $formRepository,
        CacheInterface $cache,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        
        try {
            $entityClass = 'App\\Entity\\EquipementS50';
            $startTime = microtime(true);
            
            // Récupérer les équipements de la BDD
            $equipements = $entityManager->getRepository($entityClass)->findAll();
            $structuredEquipements = $formRepository->structureLikeKizeoEquipmentsList($equipements);
            
            // Récupérer l'ID de liste et vider le cache
            $idListeKizeo = $formRepository->getIdListeKizeoPourEntite($entityClass);
            $cache->delete('kizeo_equipments_s50');
            
            // Récupérer les données Kizeo
            $kizeoEquipments = $formRepository->getAgencyListEquipementsFromKizeoByListId($idListeKizeo);
            
            // Appliquer la nouvelle logique
            $updatedEquipments = $formRepository->compareAndSyncEquipments(
                $structuredEquipements,
                $kizeoEquipments,
                $idListeKizeo
            );
            
            $endTime = microtime(true);
            
            return new JsonResponse([
                'status' => 'success',
                'entity' => 'S50',
                'results' => [
                    'bdd_count' => count($structuredEquipements),
                    'kizeo_before' => count($kizeoEquipments),
                    'kizeo_after' => count($updatedEquipments),
                    'execution_time' => round($endTime - $startTime, 2)
                ],
                'message' => 'Logique corrigée appliquée avec succès pour S50'
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Route de diagnostic pour les équipements multi-visites
     * À ajouter dans FormController
     */
    #[Route('/api/forms/diagnose/multi-visits', name: 'app_api_form_diagnose_multi_visits', methods: ['GET'])]
    public function diagnoseMultiVisitEquipments(FormRepository $formRepository): JsonResponse
    {
        try {
            $diagnostic = $formRepository->diagnoseMultiVisitEquipments();
            
            return new JsonResponse([
                'status' => 'success',
                'diagnostic' => $diagnostic,
                'insights' => $this->generateMultiVisitInsights($diagnostic)
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Génère des insights sur la répartition des visites
     */
    private function generateMultiVisitInsights(array $diagnostic): array
    {
        $insights = [];
        
        // Analyse de la distribution
        $visitDistribution = $diagnostic['visit_distribution'];
        $maxVisits = max(array_keys($visitDistribution));
        $equipmentsWithMultipleVisits = array_sum(
            array_filter($visitDistribution, fn($count, $visits) => $visits > 1, ARRAY_FILTER_USE_BOTH)
        );
        
        $insights[] = "Total d'équipements: " . $diagnostic['total_equipments'];
        $insights[] = "Équipements uniques: " . count($diagnostic['equipment_groups']);
        $insights[] = "Équipements avec plusieurs visites: " . $equipmentsWithMultipleVisits;
        $insights[] = "Maximum de visites par équipement: " . $maxVisits;
        
        // Identifier les équipements problématiques potentiels
        $problematicEquipments = array_filter(
            $diagnostic['equipment_groups'],
            fn($group) => $group['visit_count'] > 4 // Plus de 4 visites = suspect
        );
        
        if (!empty($problematicEquipments)) {
            $insights[] = "Équipements avec beaucoup de visites (>4): " . count($problematicEquipments);
        }
        
        return $insights;
    }

    // /**
    //  * Route pour tester la synchronisation sur un échantillon
    //  * À ajouter dans FormController
    //  */
    // #[Route('/api/forms/test/sync-sample', name: 'app_api_form_test_sync_sample', methods: ['POST'])]
    // public function testSyncSample(
    //     FormRepository $formRepository,
    //     EntityManagerInterface $entityManager,
    //     CacheInterface $cache,
    //     Request $request
    // ): JsonResponse {
    //     Request::enableHttpMethodParameterOverride();
    //     try {
    //         $entityClass = $request->get('entity', 'App\\Entity\\EquipementS50');
    //         $maxEquipments = (int) $request->get('max_equipments', 50);
            
    //         // Récupérer un échantillon d'équipements
    //         $equipements = $entityManager->getRepository($entityClass)
    //             ->createQueryBuilder('e')
    //             ->setMaxResults($maxEquipments)
    //             ->getQuery()
    //             ->getResult();
            
    //         // Structurer pour Kizeo
    //         $structuredEquipements = $formRepository->structureLikeKizeoEquipmentsList($equipements);
            
    //         // Récupérer les données Kizeo actuelles
    //         $idListeKizeo = $formRepository->getIdListeKizeoPourEntite($entityClass);
    //         $kizeoEquipments = $formRepository->getAgencyListEquipementsFromKizeoByListId($idListeKizeo);
            
    //         // Simuler la synchronisation SANS envoyer à Kizeo
    //         $beforeCount = count($kizeoEquipments);
    //         $afterEquipments = $formRepository->simulateSync($structuredEquipements, $kizeoEquipments);
    //         $afterCount = count($afterEquipments);
            
    //         return new JsonResponse([
    //             'status' => 'success',
    //             'test_results' => [
    //                 'entity' => $entityClass,
    //                 'bdd_equipment_count' => count($structuredEquipements),
    //                 'kizeo_before_count' => $beforeCount,
    //                 'kizeo_after_count' => $afterCount,
    //                 'would_add' => $afterCount - $beforeCount,
    //                 'sample_bdd_keys' => array_slice(
    //                     array_map(fn($e) => explode('|', $e)[0], $structuredEquipements),
    //                     0,
    //                     5
    //                 ),
    //                 'sample_kizeo_keys' => array_slice(
    //                     array_map(fn($e) => explode('|', $e)[0], $kizeoEquipments),
    //                     0,
    //                     5
    //                 )
    //             ]
    //         ], Response::HTTP_OK);
            
    //     } catch (\Exception $e) {
    //         return new JsonResponse([
    //             'status' => 'error',
    //             'error' => $e->getMessage()
    //         ], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     }
    // }

    /**
     * Méthode de simulation à ajouter dans FormRepository
     * Simule la synchronisation sans envoyer à Kizeo
     */
    public function simulateSync($structuredEquipements, $kizeoEquipments): array
    {
        $updatedKizeoEquipments = $kizeoEquipments;

        foreach ($structuredEquipements as $structuredEquipment) {
            $structuredFullKey = explode('|', $structuredEquipment)[0];
            $keyParts = explode('\\', $structuredFullKey);
            $equipmentBaseKey = ($keyParts[0] ?? '') . '\\' . ($keyParts[2] ?? '');

            $foundAndReplaced = false;
            
            foreach ($updatedKizeoEquipments as $key => $kizeoEquipment) {
                $kizeoFullKey = explode('|', $kizeoEquipment)[0];

                if ($kizeoFullKey === $structuredFullKey) {
                    $updatedKizeoEquipments[$key] = $structuredEquipment;
                    $foundAndReplaced = true;
                    break;
                }
            }

            if (!$foundAndReplaced) {
                $updatedKizeoEquipments[] = $structuredEquipment;
            }

            // Simulation de updateAllVisitsForEquipment
            $this->simulateUpdateAllVisits($updatedKizeoEquipments, $equipmentBaseKey, $structuredEquipment);
        }

        return $updatedKizeoEquipments;
    }

    /**
     * Simulation de updateAllVisitsForEquipment
     */
    private function simulateUpdateAllVisits(&$kizeoEquipments, $equipmentBaseKey, $newEquipment): void
    {
        $newEquipmentData = explode('|', $newEquipment);
        $newEquipmentFullKey = $newEquipmentData[0];
        
        foreach ($kizeoEquipments as $key => $kizeoEquipment) {
            $kizeoEquipmentData = explode('|', $kizeoEquipment);
            $kizeoFullKey = $kizeoEquipmentData[0];
            
            $kizeoKeyParts = explode('\\', $kizeoFullKey);
            $kizeoBaseKey = ($kizeoKeyParts[0] ?? '') . '\\' . ($kizeoKeyParts[2] ?? '');
            
            if ($kizeoBaseKey === $equipmentBaseKey && $kizeoFullKey !== $newEquipmentFullKey) {
                // Simulation de la mise à jour des données techniques
                for ($i = 2; $i < count($newEquipmentData); $i++) {
                    if (isset($newEquipmentData[$i])) {
                        if (isset($kizeoEquipmentData[$i])) {
                            $kizeoEquipmentData[$i] = $newEquipmentData[$i];
                        } else {
                            $kizeoEquipmentData[] = $newEquipmentData[$i];
                        }
                    }
                }
                
                $kizeoEquipments[$key] = implode('|', $kizeoEquipmentData);
            }
        }
    }

    /**
     * SOLUTION CORRIGÉE : Compare et synchronise les équipements entre BDD et Kizeo
     * 
     * Logique : 
     * 1. Pour chaque équipement BDD, vérifier s'il existe déjà sur Kizeo (peu importe la visite)
     * 2. Si l'équipement existe déjà, mettre à jour toutes ses visites avec les nouvelles données
     * 3. Si l'équipement n'existe pas du tout, l'ajouter
     */
    private function compareAndSyncEquipments($structuredEquipements, $kizeoEquipments, $idListeKizeo, FormRepository $formRepository): array 
    {
        $updatedKizeoEquipments = $kizeoEquipments;

        foreach ($structuredEquipements as $structuredEquipment) {
            $structuredFullKey = explode('|', $structuredEquipment)[0];
            $keyParts = explode('\\', $structuredFullKey);
            $equipmentBaseKey = ($keyParts[0] ?? '') . '\\' . ($keyParts[2] ?? ''); // RAISON_SOCIALE\NUMERO_EQUIPEMENT

            // Vérifier si cet équipement existe déjà sur Kizeo (peu importe la visite)
            $equipmentExistsInKizeo = $this->equipmentExistsInKizeo($updatedKizeoEquipments, $equipmentBaseKey);
            
            if ($equipmentExistsInKizeo) {
                // L'équipement existe déjà : mettre à jour toutes ses visites
                $this->$formRepository->updateAllVisitsForEquipment($updatedKizeoEquipments, $equipmentBaseKey, $structuredEquipment);
                
                // Vérifier si la visite spécifique existe, sinon l'ajouter
                $specificVisitExists = $this->specificVisitExists($updatedKizeoEquipments, $structuredFullKey);
                if (!$specificVisitExists) {
                    $updatedKizeoEquipments[] = $structuredEquipment;
                }
            } else {
                // L'équipement n'existe pas du tout : l'ajouter
                $updatedKizeoEquipments[] = $structuredEquipment;
            }
        }

        $this->$formRepository->envoyerListeKizeo($updatedKizeoEquipments, $idListeKizeo);
        return $updatedKizeoEquipments;
    }

    /**
     * Vérifie si un équipement existe déjà dans Kizeo (peu importe la visite)
     */
    private function equipmentExistsInKizeo($kizeoEquipments, $equipmentBaseKey): bool
    {
        foreach ($kizeoEquipments as $kizeoEquipment) {
            $kizeoFullKey = explode('|', $kizeoEquipment)[0];
            $kizeoKeyParts = explode('\\', $kizeoFullKey);
            $kizeoBaseKey = ($kizeoKeyParts[0] ?? '') . '\\' . ($kizeoKeyParts[2] ?? '');
            
            if ($kizeoBaseKey === $equipmentBaseKey) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie si une visite spécifique existe déjà
     */
    private function specificVisitExists($kizeoEquipments, $structuredFullKey): bool
    {
        foreach ($kizeoEquipments as $kizeoEquipment) {
            $kizeoFullKey = explode('|', $kizeoEquipment)[0];
            if ($kizeoFullKey === $structuredFullKey) {
                return true;
            }
        }
        return false;
    }

    // AJOUTER cette route ultra-simple dans FormController.php pour tester :

    /**
     * Route de test ultra-simple pour vérifier que les méthodes existent
     */
    #[Route('/api/forms/test/ultra-simple', name: 'app_api_form_test_ultra_simple', methods: ['GET'])]
    public function testUltraSimple(FormRepository $formRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Test 1: Vérifier qu'on peut récupérer des équipements
            $equipements = $entityManager->getRepository('App\\Entity\\EquipementS50')
                ->createQueryBuilder('e')
                ->where('e.raisonSociale LIKE :eurial')
                ->setParameter('eurial', 'EURIAL SAS CREST%')
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();
            
            // Test 2: Vérifier qu'on peut structurer les équipements
            $structured = $formRepository->structureLikeKizeoEquipmentsList($equipements);
            
            // Test 3: Vérifier qu'on peut récupérer l'ID de liste
            $idListe = $formRepository->getIdListeKizeoPourEntite('App\\Entity\\EquipementS50');
            
            return new JsonResponse([
                'status' => 'success',
                'tests' => [
                    'equipments_found' => count($equipements),
                    'structured_count' => count($structured),
                    'list_id' => $idListe,
                    'first_structured' => isset($structured[0]) ? $structured[0] : null
                ]
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Version avec debugging détaillé pour comprendre le comportement
     */
    private function compareAndSyncEquipmentsWithDetailedLogging($structuredEquipements, $kizeoEquipments, $idListeKizeo, FormRepository $formRepository): array 
    {
        
        $updatedKizeoEquipments = $kizeoEquipments;
        $stats = [
            'processed_equipment' => 0,
            'equipment_already_exists' => 0,
            'new_equipment_added' => 0,
            'specific_visits_added' => 0,
            'visits_updated' => 0,
            'details' => []
        ];

        foreach ($structuredEquipements as $structuredEquipment) {
            $structuredFullKey = explode('|', $structuredEquipment)[0];
            $keyParts = explode('\\', $structuredFullKey);
            $equipmentBaseKey = ($keyParts[0] ?? '') . '\\' . ($keyParts[2] ?? '');
            $visit = $keyParts[1] ?? '';

            $stats['processed_equipment']++;
            
            $equipmentExistsInKizeo = $this->equipmentExistsInKizeo($updatedKizeoEquipments, $equipmentBaseKey);
            $specificVisitExists = $this->specificVisitExists($updatedKizeoEquipments, $structuredFullKey);
            
            $action = '';
            
            if ($equipmentExistsInKizeo) {
                $stats['equipment_already_exists']++;
                $action .= 'Equipment exists, ';
                
                // Mettre à jour toutes les visites existantes
                $updatedCount = $this->updateAllVisitsForEquipmentWithCount($updatedKizeoEquipments, $equipmentBaseKey, $structuredEquipment);
                $stats['visits_updated'] += $updatedCount;
                $action .= "$updatedCount visits updated, ";
                
                // Ajouter la visite spécifique si elle n'existe pas
                if (!$specificVisitExists) {
                    $updatedKizeoEquipments[] = $structuredEquipment;
                    $stats['specific_visits_added']++;
                    $action .= 'specific visit added';
                } else {
                    $action .= 'specific visit already exists';
                }
            } else {
                $updatedKizeoEquipments[] = $structuredEquipment;
                $stats['new_equipment_added']++;
                $action = 'New equipment added';
            }
            
            $stats['details'][] = [
                'equipment_key' => $equipmentBaseKey,
                'visit' => $visit,
                'full_key' => $structuredFullKey,
                'existed_in_kizeo' => $equipmentExistsInKizeo,
                'specific_visit_existed' => $specificVisitExists,
                'action' => $action
            ];
        }

        // Log détaillé
        error_log("DETAILED SYNC STATS: " . json_encode($stats, JSON_PRETTY_PRINT));

        $this->$formRepository->envoyerListeKizeo($updatedKizeoEquipments, $idListeKizeo);
        return $updatedKizeoEquipments;
    }

    /**
     * Version de updateAllVisitsForEquipment qui retourne le nombre de mises à jour
     */
    private function updateAllVisitsForEquipmentWithCount(&$kizeoEquipments, $equipmentBaseKey, $newEquipment): int
    {
        $newEquipmentData = explode('|', $newEquipment);
        $newEquipmentFullKey = $newEquipmentData[0];
        $updateCount = 0;
        
        foreach ($kizeoEquipments as $key => $kizeoEquipment) {
            $kizeoEquipmentData = explode('|', $kizeoEquipment);
            $kizeoFullKey = $kizeoEquipmentData[0];
            
            $kizeoKeyParts = explode('\\', $kizeoFullKey);
            $kizeoBaseKey = ($kizeoKeyParts[0] ?? '') . '\\' . ($kizeoKeyParts[2] ?? '');
            
            if ($kizeoBaseKey === $equipmentBaseKey && $kizeoFullKey !== $newEquipmentFullKey) {
                // Mettre à jour les données techniques
                for ($i = 2; $i < count($newEquipmentData); $i++) {
                    if (isset($newEquipmentData[$i])) {
                        if (isset($kizeoEquipmentData[$i])) {
                            $kizeoEquipmentData[$i] = $newEquipmentData[$i];
                        } else {
                            $kizeoEquipmentData[] = $newEquipmentData[$i];
                        }
                    }
                }
                
                $kizeoEquipments[$key] = implode('|', $kizeoEquipmentData);
                $updateCount++;
            }
        }
        
        return $updateCount;
    }

    

    /**
     * Simulation avec logging pour test
     */
    private function simulateSyncWithLogging($structuredEquipements, $kizeoEquipments): array
    {
        $updatedKizeoEquipments = $kizeoEquipments;
        
        echo "=== SIMULATION SYNC ===\n";
        echo "BDD Equipment count: " . count($structuredEquipements) . "\n";
        echo "Kizeo Equipment count: " . count($kizeoEquipments) . "\n\n";
        
        foreach ($structuredEquipements as $index => $structuredEquipment) {
            $structuredFullKey = explode('|', $structuredEquipment)[0];
            $keyParts = explode('\\', $structuredFullKey);
            $equipmentBaseKey = ($keyParts[0] ?? '') . '\\' . ($keyParts[2] ?? '');
            
            echo "Processing #$index: $equipmentBaseKey\n";
            
            $equipmentExistsInKizeo = $this->equipmentExistsInKizeo($updatedKizeoEquipments, $equipmentBaseKey);
            $specificVisitExists = $this->specificVisitExists($updatedKizeoEquipments, $structuredFullKey);
            
            echo "  - Equipment exists in Kizeo: " . ($equipmentExistsInKizeo ? 'YES' : 'NO') . "\n";
            echo "  - Specific visit exists: " . ($specificVisitExists ? 'YES' : 'NO') . "\n";
            
            if ($equipmentExistsInKizeo) {
                $updateCount = $this->updateAllVisitsForEquipmentWithCount($updatedKizeoEquipments, $equipmentBaseKey, $structuredEquipment);
                echo "  - Updated $updateCount existing visits\n";
                
                if (!$specificVisitExists) {
                    $updatedKizeoEquipments[] = $structuredEquipment;
                    echo "  - Added specific visit\n";
                }
            } else {
                $updatedKizeoEquipments[] = $structuredEquipment;
                echo "  - Added new equipment\n";
            }
            
            echo "\n";
        }
        
        echo "Final count: " . count($updatedKizeoEquipments) . "\n";
        echo "=== END SIMULATION ===\n";
        
        return $updatedKizeoEquipments;
    }

    /**
     * Route de test pour la nouvelle logique de synchronisation
     * À ajouter dans FormController
     */
    #[Route('/api/forms/test/new-sync-logic', name: 'app_api_form_test_new_sync_logic', methods: ['GET'])]
    public function testNewSyncLogic(FormRepository $formRepository): JsonResponse
    {
        try {
            $testResult = $formRepository->testSyncLogicWithSample('App\\Entity\\EquipementS50', $formRepository);
            
            return new JsonResponse([
                'status' => 'success',
                'test_result' => $testResult,
                'analysis' => [
                    'will_duplicate' => $testResult['difference'] > 0,
                    'explanation' => $testResult['difference'] > 0 
                        ? 'La logique actuelle va ajouter ' . $testResult['difference'] . ' équipements en doublon'
                        : 'La logique semble correcte - pas de duplication prévue'
                ]
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Route pour appliquer temporairement la nouvelle logique sur une seule entité
     */
    #[Route('/api/forms/apply/new-sync-logic/{entity}', name: 'app_api_form_apply_new_sync_logic', methods: ['POST'])]
    public function applyNewSyncLogic(
        string $entity,
        FormRepository $formRepository, 
        CacheInterface $cache, 
        EntityManagerInterface $entityManager
    ): JsonResponse {
        
        try {
            // Valider l'entité
            $validEntities = [
                's10' => 'App\\Entity\\EquipementS10',
                's40' => 'App\\Entity\\EquipementS40', 
                's50' => 'App\\Entity\\EquipementS50',
                's60' => 'App\\Entity\\EquipementS60',
                's70' => 'App\\Entity\\EquipementS70',
                's80' => 'App\\Entity\\EquipementS80',
                's100' => 'App\\Entity\\EquipementS100',
                's120' => 'App\\Entity\\EquipementS120',
                's130' => 'App\\Entity\\EquipementS130',
                's140' => 'App\\Entity\\EquipementS140',
                's150' => 'App\\Entity\\EquipementS150',
                's160' => 'App\\Entity\\EquipementS160',
                's170' => 'App\\Entity\\EquipementS170',
            ];
            
            if (!isset($validEntities[$entity])) {
                return new JsonResponse([
                    'status' => 'error',
                    'error' => 'Entité non valide. Utilisez: ' . implode(', ', array_keys($validEntities))
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $entityClass = $validEntities[$entity];
            
            // Appliquer la nouvelle logique pour cette entité uniquement
            $startTime = microtime(true);
            
            // Récupérer les équipements de la BDD
            $equipements = $entityManager->getRepository($entityClass)->findAll();
            $structuredEquipements = $formRepository->structureLikeKizeoEquipmentsList($equipements);
            
            // Récupérer l'ID de liste et vider le cache
            $idListeKizeo = $formRepository->getIdListeKizeoPourEntite($entityClass);
            $cache->delete('kizeo_equipments_' . $entity);
            
            // Récupérer les données Kizeo
            $kizeoEquipments = $formRepository->getAgencyListEquipementsFromKizeoByListId($idListeKizeo);
            
            // Appliquer la nouvelle logique avec logging détaillé
            $updatedEquipments = $formRepository->compareAndSyncEquipmentsWithDetailedLogging(
                $structuredEquipements,
                $kizeoEquipments,
                $idListeKizeo
            );
            
            $endTime = microtime(true);
            
            return new JsonResponse([
                'status' => 'success',
                'entity' => $entity,
                'results' => [
                    'bdd_count' => count($structuredEquipements),
                    'kizeo_before' => count($kizeoEquipments),
                    'kizeo_after' => count($updatedEquipments),
                    'execution_time' => round($endTime - $startTime, 2)
                ],
                'message' => 'Nouvelle logique appliquée avec succès pour ' . $entity
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Route pour vérifier le statut du cache Redis (optionnel - pour monitoring)
     */
    #[Route('/api/forms/cache/status', name: 'app_api_form_cache_status', methods: ['GET'])]
    public function getCacheStatus(CacheInterface $cache): JsonResponse
    {
        try {
            // Test de connexion au cache
            $testKey = 'cache_test_' . time();
            $cache->get($testKey, function(ItemInterface $item) {
                $item->expiresAfter(10);
                return 'test_value';
            });
            
            // Suppression de la clé de test
            $cache->delete($testKey);
            
            // Information sur les clés existantes liées aux équipements
            $cacheInfo = [
                'status' => 'connected',
                'type' => 'redis',
                'test_passed' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            return new JsonResponse($cacheInfo, Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Route pour vider le cache des équipements Kizeo (optionnel - pour maintenance)
     */
    #[Route('/api/forms/cache/clear/kizeo', name: 'app_api_form_cache_clear_kizeo', methods: ['DELETE'])]
    public function clearKizeoCache(CacheInterface $cache): JsonResponse
    {
        try {
            $clearedKeys = [];
            
            // Liste des entités pour construire les clés de cache
            $entities = ['s10', 's40', 's50', 's60', 's70', 's80', 's100', 's120', 's130', 's140', 's150', 's160', 's170'];
            
            foreach ($entities as $entity) {
                $cacheKey = 'kizeo_equipments_' . $entity;
                if ($cache->delete($cacheKey)) {
                    $clearedKeys[] = $cacheKey;
                }
            }
            
            return new JsonResponse([
                'message' => 'Cache Kizeo vidé avec succès',
                'cleared_keys' => $clearedKeys,
                'count' => count($clearedKeys),
                'timestamp' => date('Y-m-d H:i:s')
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Erreur lors du vidage du cache',
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 
     * Save PDF maintenance on remote server --                                  THIRD CALL IN CRON TASK
     * LA MISE A JOUR DES PDF A ETE ENLEVEE CAR KIZEO GERE LE FTP DES PDF EN DIRECT A CHAQUE UPLOAD SUR KIZEO
     */
    #[Route('/api/forms/save/maintenance/pdf', name: 'app_api_form_save_maintenance_pdf', methods: ['GET'])]
    public function savePdfInAssetsPdfFolder(FormRepository $formRepository, CacheInterface $cache) : JsonResponse
    {
        $formRepository->savePdfInAssetsPdfFolder($cache);
        
        // return $this->redirectToRoute('app_api_form_save_maintenance_equipments'); // DDécommenter et commenter  : JsonResponse pour faire une boucle fermée des 3 url pour mettre à jour si les tâches cron ne marchent pas
        return new JsonResponse("Les pdf de maintenance ont bien été sauvegardés + on est à jour en BDD et sur KIZEO ", Response::HTTP_OK, [], true);
    }
    
    /**
     * NOUVELLE ROUTE : Démarrage du processus asynchrone pour markasunread
     *
     * ROUTE CORRIGÉE : Démarrage du processus asynchrone
     */
    #[Route('/api/forms/markasunread', name: 'app_api_form_markasunread', methods: ['GET'])]
    public function markMaintenanceFormsAsUnread(FormRepository $formRepository, CacheInterface $cache): JsonResponse
    {
        // CORRECTION : Passer le bon nombre de paramètres à uniqid()
        $processId = uniqid('mark_unread_', true); // 2 paramètres : prefix et more_entropy
        
        try {
            // Initialiser le statut du processus en cache
            $initialStatus = [
                'status' => 'started',
                'started_at' => date('Y-m-d H:i:s'),
                'progress' => 0,
                'total' => 0,
                'success_count' => 0,
                'error_count' => 0,
                'processed' => 0,
                'current_form' => null,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
            // CORRECTION : Stocker le statut initial avec la nouvelle méthode
            $cache->delete("mark_unread_status_$processId");
            $cache->get("mark_unread_status_$processId", function($item) use ($initialStatus) {
                $item->expiresAfter(7200); // 2 heures
                return $initialStatus;
            });
            
            // Retourner immédiatement la réponse à l'utilisateur
            $response = new JsonResponse([
                'success' => true,
                'message' => 'Processus de marquage démarré en arrière-plan',
                'process_id' => $processId,
                'status_url' => "/api/forms/markasunread/status/$processId",
                'started_at' => date('Y-m-d H:i:s')
            ]);
            
            // Fermer la connexion HTTP immédiatement
            $response->send();
            
            // Terminer la réponse pour PHP-FPM ou Apache
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                // Pour Apache mod_php
                if (ob_get_level()) {
                    ob_end_flush();
                }
                flush();
            }
            
            // Maintenant démarrer le traitement en arrière-plan
            $this->processMarkUnreadAsync($formRepository, $cache, $processId);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors du démarrage du processus: ' . $e->getMessage()
            ], 500);
        }
        
        // Cette ligne ne sera jamais atteinte si fastcgi_finish_request() fonctionne
        return $response;
    }

    /**
     * ROUTE CORRIGÉE : Vérification du statut du processus
     */
    #[Route('/api/forms/markasunread/status/{processId}', name: 'app_api_form_markasunread_status', methods: ['GET'])]
    public function getMarkUnreadStatus(string $processId, CacheInterface $cache): JsonResponse
    {
        try {
            $status = null;
            $found = false;
            
            // Utiliser get() avec un callback pour vérifier si la clé existe
            try {
                $status = $cache->get("mark_unread_status_$processId", function() use (&$found) {
                    $found = false;
                    return null; // Cette valeur ne sera pas utilisée si la clé n'existe pas
                });
                $found = true; // Si on arrive ici, c'est que la clé existe
            } catch (\Psr\Cache\InvalidArgumentException $e) {
                $found = false;
            }
            
            // Si le statut n'a pas été trouvé ou est null/vide
            if (!$found || !$status) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Processus non trouvé ou expiré',
                    'process_id' => $processId
                ], 404);
            }
            
            return new JsonResponse([
                'success' => true,
                'process_id' => $processId,
                'data' => $status
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération du statut: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * TRAITEMENT ASYNCHRONE CORRIGÉ
     */
    private function processMarkUnreadAsync(FormRepository $formRepository, CacheInterface $cache, string $processId): void
    {
        $startTime = time();
        $maxExecutionTime = 1800; // 30 minutes maximum
        
        try {
            // Configuration pour traitement long
            set_time_limit(0); // Pas de limite de temps
            ini_set('memory_limit', '512M');
            ignore_user_abort(true); // Continue même si l'utilisateur ferme son navigateur
            
            error_log("🚀 Début processus async $processId à " . date('Y-m-d H:i:s'));
            
            // Mettre à jour le statut : en cours de récupération des formulaires
            $this->updateAsyncStatus($cache, $processId, [
                'status' => 'fetching_forms',
                'message' => 'Récupération de la liste des formulaires...'
            ]);
            
            error_log("🔍 Récupération de la liste des formulaires MAINTENANCE...");
            
            // Récupérer la liste des formulaires MAINTENANCE
            $maintenanceForms = $this->getMaintenanceFormsForAsync();
            $totalForms = count($maintenanceForms);
            
            error_log("📊 $totalForms formulaires de maintenance trouvés");
            
            if ($totalForms === 0) {
                error_log("⚠️ Aucun formulaire de maintenance trouvé - Arrêt du processus");
                $this->updateAsyncStatus($cache, $processId, [
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'message' => 'Aucun formulaire de maintenance trouvé',
                    'progress' => 100
                ]);
                return;
            }
            
            // Mettre à jour avec le total
            $this->updateAsyncStatus($cache, $processId, [
                'status' => 'processing',
                'total' => $totalForms,
                'message' => "Traitement de $totalForms formulaires..."
            ]);
            
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            error_log("🔄 Début du traitement des formulaires...");
            
            // Traiter chaque formulaire
            foreach ($maintenanceForms as $index => $form) {
                $currentTime = time();
                $elapsedTime = $currentTime - $startTime;
                
                // Vérifier le timeout global
                if ($elapsedTime > $maxExecutionTime) {
                    error_log("⏰ TIMEOUT GLOBAL après $elapsedTime secondes (limite: $maxExecutionTime)");
                    $this->updateAsyncStatus($cache, $processId, [
                        'status' => 'failed',
                        'failed_at' => date('Y-m-d H:i:s'),
                        'error' => "Timeout global après $elapsedTime secondes",
                        'message' => 'Processus arrêté pour éviter le blocage - Temps dépassé'
                    ]);
                    return;
                }
                
                $formId = $form['id'];
                $formName = $form['name'];
                $formPosition = $index + 1;
                
                error_log("📝 [$formPosition/$totalForms] Début traitement formulaire $formId ($formName)");
                error_log("⏱️ Temps écoulé: {$elapsedTime}s / {$maxExecutionTime}s");
                
                try {
                    // Mettre à jour le formulaire en cours
                    $this->updateAsyncStatus($cache, $processId, [
                        'current_form' => [
                            'id' => $formId,
                            'name' => $formName,
                            'index' => $formPosition
                        ],
                        'message' => "Traitement: $formName ($formPosition/$totalForms)",
                        'elapsed_time' => $elapsedTime
                    ]);
                    
                    error_log("🔍 Récupération des data_ids pour formulaire $formId...");
                    
                    // Récupérer les data_ids pour ce formulaire avec timeout
                    $dataStartTime = time();
                    $dataIds = $this->getDataIdsForAsync($formId);
                    $dataEndTime = time();
                    $dataRetrievalTime = $dataEndTime - $dataStartTime;
                    
                    error_log("📊 Formulaire $formId : " . count($dataIds) . " data_ids récupérés en {$dataRetrievalTime}s");
                    
                    if (!empty($dataIds)) {
                        error_log("🔄 Marquage de " . count($dataIds) . " data_ids comme 'non lus' pour formulaire $formId...");
                        
                        // Marquer comme non lu avec mesure du temps
                        $markStartTime = time();
                        $this->markFormAsUnreadForAsync($formId, $dataIds);
                        $markEndTime = time();
                        $markingTime = $markEndTime - $markStartTime;
                        
                        $successCount++;
                        
                        error_log("✅ Formulaire $formId ($formName) marqué avec succès !");
                        error_log("📊 - " . count($dataIds) . " data_ids traités en {$markingTime}s");
                        error_log("📊 - Total succès: $successCount, erreurs: $errorCount");
                    } else {
                        error_log("⚠️ Aucun data_id trouvé pour formulaire $formId ($formName) - Passage au suivant");
                    }
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $errorDetail = [
                        'form_id' => $formId,
                        'form_name' => $formName,
                        'error' => $e->getMessage(),
                        'timestamp' => date('Y-m-d H:i:s'),
                        'elapsed_time' => $elapsedTime
                    ];
                    $errors[] = $errorDetail;
                    
                    error_log("❌ ERREUR formulaire $formId ($formName): " . $e->getMessage());
                    error_log("📊 Total succès: $successCount, erreurs: $errorCount");
                }
                
                // Calculer et mettre à jour le progrès
                $processed = $index + 1;
                $progress = round(($processed / $totalForms) * 100, 2);
                
                error_log("📈 Progression: $progress% ($processed/$totalForms formulaires)");
                
                $this->updateAsyncStatus($cache, $processId, [
                    'processed' => $processed,
                    'progress' => $progress,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'elapsed_time' => $elapsedTime
                ]);
                
                // Petite pause pour éviter la surcharge de l'API
                error_log("⏸️ Pause de 0.15s avant le prochain formulaire...");
                usleep(150000); // 0.15 seconde
            }
            
            $totalTime = time() - $startTime;
            error_log("🎉 FIN DU TRAITEMENT - Processus $processId terminé en {$totalTime}s");
            error_log("📊 RÉSULTATS FINAUX:");
            error_log("   - Total formulaires: $totalForms");
            error_log("   - Succès: $successCount");
            error_log("   - Erreurs: $errorCount");
            error_log("   - Taux de réussite: " . ($totalForms > 0 ? round(($successCount / $totalForms) * 100, 2) : 0) . "%");
            
            // Statut final de completion
            $this->updateAsyncStatus($cache, $processId, [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'progress' => 100,
                'current_form' => null,
                'message' => "Terminé ! Succès: $successCount, Erreurs: $errorCount",
                'total_execution_time' => $totalTime,
                'final_summary' => [
                    'total_processed' => $totalForms,
                    'successful' => $successCount,
                    'failed' => $errorCount,
                    'success_rate' => $totalForms > 0 ? round(($successCount / $totalForms) * 100, 2) : 0,
                    'execution_time_seconds' => $totalTime
                ],
                'errors' => $errors
            ]);
            
            error_log("💾 Statut final sauvegardé dans le cache");
            
        } catch (\Exception $e) {
            $totalTime = time() - $startTime;
            error_log("💥 ERREUR CRITIQUE dans processMarkUnreadAsync après {$totalTime}s:");
            error_log("   - Processus: $processId");
            error_log("   - Erreur: " . $e->getMessage());
            error_log("   - Fichier: " . $e->getFile() . ":" . $e->getLine());
            error_log("   - Stack trace: " . $e->getTraceAsString());
            
            // Erreur critique dans tout le processus
            $this->updateAsyncStatus($cache, $processId, [
                'status' => 'failed',
                'failed_at' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
                'message' => 'Erreur critique: ' . $e->getMessage(),
                'execution_time_before_failure' => $totalTime,
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage()
                ]
            ]);
            
            error_log("💾 Statut d'erreur sauvegardé dans le cache");
        }
    }

    /**
     * CORRECTION : Récupération des formulaires MAINTENANCE 
     * (suppression du paramètre $cache non utilisé)
     */
    private function getMaintenanceFormsForAsync(): array
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 30
                ]
            );
            
            $content = $response->toArray();
            $maintenanceForms = [];
            
            foreach ($content['forms'] as $form) {
                if ($form['class'] == "MAINTENANCE") {
                    $maintenanceForms[] = [
                        'id' => $form['id'],
                        'name' => $form['name'] ?? 'Formulaire ' . $form['id']
                    ];
                }
            }
            
            return $maintenanceForms;
            
        } catch (\Exception $e) {
            error_log("Erreur getMaintenanceFormsForAsync: " . $e->getMessage());
            throw new \Exception("Impossible de récupérer la liste des formulaires: " . $e->getMessage());
        }
    }

    /**
     * CORRECTION : Récupération des data_ids
     */
    private function getDataIdsForAsync($formId): array
    {
        try {
            error_log("🔍 [getDataIdsForAsync] Début récupération data_ids pour formulaire $formId");
            
            $startTime = microtime(true);
            
            $response = $this->client->request('POST', 
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 30 // Timeout à 30 secondes max
                ]
            );
            
            $requestTime = round((microtime(true) - $startTime) * 1000, 2);
            error_log("🌐 [getDataIdsForAsync] Requête API terminée en {$requestTime}ms pour formulaire $formId");
            
            $content = $response->toArray();
            $dataIds = [];
            
            if (isset($content['data']) && is_array($content['data'])) {
                foreach ($content['data'] as $dataItem) {
                    if (isset($dataItem['_id'])) {
                        $dataIds[] = intval($dataItem['_id']);
                    }
                }
            }
            
            error_log("✅ [getDataIdsForAsync] Formulaire $formId : " . count($dataIds) . " data_ids trouvés");
            
            if (count($dataIds) > 50) {
                error_log("⚠️ [getDataIdsForAsync] ATTENTION: Formulaire $formId a " . count($dataIds) . " data_ids (traitement long prévu)");
            }
            
            return $dataIds;
            
        } catch (\Symfony\Component\HttpClient\Exception\TimeoutException $e) {
            error_log("⏰ [getDataIdsForAsync] TIMEOUT formulaire $formId après 30 secondes");
            throw new \Exception("Timeout lors de la récupération des data_ids pour formulaire $formId");
        } catch (\Exception $e) {
            error_log("❌ [getDataIdsForAsync] ERREUR formulaire $formId: " . $e->getMessage());
            throw new \Exception("Erreur récupération data_ids pour formulaire $formId: " . $e->getMessage());
        }
    }

    /**
     * Version corrigée du marquage avec timeout et retry
     */
    private function markFormAsUnreadForAsync($formId, $dataIds): void
    {
        try {
            $dataCount = count($dataIds);
            error_log("🔄 [markFormAsUnreadForAsync] Début marquage formulaire $formId avec $dataCount data_ids");
            
            $startTime = microtime(true);
            
            $response = $this->client->request('POST', 
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/markasunreadbyaction/read', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'json' => ["data_ids" => $dataIds],
                    'timeout' => 60 // Timeout plus long pour le marquage
                ]
            );
            
            $requestTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Vérifier que la requête s'est bien passée
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                error_log("✅ [markFormAsUnreadForAsync] Succès: Formulaire $formId marqué comme non lu en {$requestTime}ms");
                error_log("📊 [markFormAsUnreadForAsync] $dataCount data_ids traités avec succès");
            } else {
                error_log("❌ [markFormAsUnreadForAsync] Code de statut inattendu: $statusCode pour formulaire $formId");
                throw new \Exception("Code de statut HTTP inattendu: $statusCode");
            }
            
        } catch (\Symfony\Component\HttpClient\Exception\TimeoutException $e) {
            error_log("⏰ [markFormAsUnreadForAsync] TIMEOUT formulaire $formId lors du marquage (60s dépassées)");
            throw new \Exception("Timeout lors du marquage du formulaire $formId comme non lu");
            
        } catch (\Exception $e) {
            error_log("❌ [markFormAsUnreadForAsync] ERREUR formulaire $formId lors du marquage: " . $e->getMessage());
            throw new \Exception("Erreur lors du marquage du formulaire $formId: " . $e->getMessage());
        }
    }

    /**
     * CORRECTION : Mise à jour du statut du processus asynchrone
     */
    private function updateAsyncStatus(CacheInterface $cache, string $processId, array $updates): void
    {
        try {
            $currentStatus = $cache->get("mark_unread_status_$processId", function() {
                return [];
            });
            $newStatus = array_merge($currentStatus, $updates);
            $newStatus['last_updated'] = date('Y-m-d H:i:s');
            
            // Utiliser delete puis get avec callback pour "simuler" un set
            $cache->delete("mark_unread_status_$processId");
            $cache->get("mark_unread_status_$processId", function($item) use ($newStatus) {
                $item->expiresAfter(7200); // 2 heures
                return $newStatus;
            });
            
        } catch (\Exception $e) {
            error_log("Erreur updateAsyncStatus: " . $e->getMessage());
        }
    }

    // ================================================================
    // CORRECTION HTML : Page de test sans erreur JSON
    // ================================================================

    /**
     * ROUTE CORRIGÉE pour la page de test
     */
    #[Route('/test_async', name: 'app_test_async', methods: ['GET'])]
    public function testAsyncPage(): Response
    {
        $html = '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Marquage Formulaires - Traitement Asynchrone</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #f5f5f5;
                }
                .container {
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .btn {
                    background: #007bff;
                    color: white;
                    padding: 12px 24px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                    margin: 10px 5px;
                }
                .btn:hover { background: #0056b3; }
                .btn:disabled { 
                    background: #ccc; 
                    cursor: not-allowed; 
                }
                .status-box {
                    background: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                    padding: 15px;
                    margin: 15px 0;
                    min-height: 100px;
                }
                .progress-bar {
                    width: 100%;
                    height: 20px;
                    background: #e9ecef;
                    border-radius: 10px;
                    overflow: hidden;
                    margin: 10px 0;
                }
                .progress-fill {
                    height: 100%;
                    background: #28a745;
                    transition: width 0.3s ease;
                    text-align: center;
                    line-height: 20px;
                    color: white;
                    font-size: 12px;
                }
                .status-started { border-left: 4px solid #007bff; }
                .status-processing { border-left: 4px solid #ffc107; }
                .status-completed { border-left: 4px solid #28a745; }
                .status-failed { border-left: 4px solid #dc3545; }
                .error-list {
                    background: #f8d7da;
                    border: 1px solid #f5c6cb;
                    border-radius: 4px;
                    padding: 10px;
                    margin-top: 10px;
                    max-height: 200px;
                    overflow-y: auto;
                }
                .success-info {
                    background: #d4edda;
                    border: 1px solid #c3e6cb;
                    border-radius: 4px;
                    padding: 10px;
                    margin-top: 10px;
                }
                .timestamp {
                    color: #6c757d;
                    font-size: 12px;
                }
                .current-form {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 4px;
                    padding: 8px;
                    margin: 5px 0;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🔄 Marquage des Formulaires de Maintenance</h1>
                <p>Cette page permet de marquer tous les formulaires de maintenance comme "non lus" en arrière-plan.</p>
                
                <div>
                    <button id="startBtn" class="btn" onclick="startProcess()">
                        🚀 Démarrer le processus
                    </button>
                    <button id="refreshBtn" class="btn" onclick="refreshStatus()" disabled>
                        🔄 Actualiser le statut
                    </button>
                    <button id="stopBtn" class="btn" onclick="stopChecking()" style="background: #dc3545;" disabled>
                        ⏹️ Arrêter le suivi
                    </button>
                </div>
                
                <div id="statusContainer" class="status-box" style="display: none;">
                    <h3>📊 Statut du processus</h3>
                    <div id="statusContent"></div>
                </div>
            </div>

            <script>
                let currentProcessId = null;
                let statusInterval = null;
                let isProcessRunning = false;

                async function startProcess() {
                    try {
                        document.getElementById("startBtn").disabled = true;
                        document.getElementById("startBtn").textContent = "⏳ Démarrage...";
                        
                        const response = await fetch("/api/forms/markasunread");
                        const data = await response.json();
                        
                        if (data.success) {
                            currentProcessId = data.process_id;
                            isProcessRunning = true;
                            
                            document.getElementById("statusContainer").style.display = "block";
                            document.getElementById("refreshBtn").disabled = false;
                            document.getElementById("stopBtn").disabled = false;
                            
                            showStatus({
                                status: "started",
                                message: data.message,
                                started_at: data.started_at,
                                process_id: data.process_id
                            });
                            
                            startStatusChecking();
                            
                        } else {
                            alert("Erreur: " + data.error);
                            resetButtons();
                        }
                        
                    } catch (error) {
                        console.error("Erreur:", error);
                        alert("Erreur lors du démarrage: " + error.message);
                        resetButtons();
                    }
                }

                async function refreshStatus() {
                    if (!currentProcessId) return;
                    
                    try {
                        const response = await fetch("/api/forms/markasunread/status/" + currentProcessId);
                        const data = await response.json();
                        
                        if (data.success) {
                            showStatus(data.data);
                            
                            if (data.data.status === "completed" || data.data.status === "failed") {
                                stopChecking();
                            }
                        } else {
                            showError("Erreur lors de la récupération du statut: " + data.error);
                        }
                        
                    } catch (error) {
                        console.error("Erreur:", error);
                        showError("Erreur de communication: " + error.message);
                    }
                }

                function startStatusChecking() {
                    statusInterval = setInterval(refreshStatus, 2000);
                }

                function stopChecking() {
                    if (statusInterval) {
                        clearInterval(statusInterval);
                        statusInterval = null;
                    }
                    isProcessRunning = false;
                    resetButtons();
                }

                function resetButtons() {
                    document.getElementById("startBtn").disabled = false;
                    document.getElementById("startBtn").textContent = "🚀 Démarrer le processus";
                    document.getElementById("refreshBtn").disabled = true;
                    document.getElementById("stopBtn").disabled = true;
                }

                function showStatus(status) {
                    const container = document.getElementById("statusContent");
                    const statusClass = "status-" + status.status;
                    
                    let html = "<div class=\"" + statusClass + "\">";
                    html += "<h4>📍 Statut: " + getStatusText(status.status) + "</h4>";
                    html += "<p><strong>ID du processus:</strong> " + currentProcessId + "</p>";
                    html += "<p><strong>Message:</strong> " + (status.message || "En cours...") + "</p>";
                    html += "<p class=\"timestamp\"><strong>Dernière mise à jour:</strong> " + (status.last_updated || "N/A") + "</p>";
                    
                    if (status.progress !== undefined) {
                        html += "<div class=\"progress-bar\">";
                        html += "<div class=\"progress-fill\" style=\"width: " + status.progress + "%\">";
                        html += status.progress + "%";
                        html += "</div></div>";
                    }
                    
                    if (status.total > 0) {
                        html += "<p><strong>Progression:</strong> " + (status.processed || 0) + " / " + status.total + " formulaires</p>";
                        html += "<p><strong>Succès:</strong> " + (status.success_count || 0) + " | <strong>Erreurs:</strong> " + (status.error_count || 0) + "</p>";
                    }
                    
                    if (status.current_form) {
                        html += "<div class=\"current-form\">";
                        html += "<strong>📝 En cours:</strong> " + status.current_form.name + " ";
                        html += "(" + status.current_form.index + "/" + status.total + ")";
                        html += "</div>";
                    }
                    
                    if (status.final_summary) {
                        html += "<div class=\"success-info\">";
                        html += "<h5>✅ Résumé final</h5>";
                        html += "<p><strong>Total traité:</strong> " + status.final_summary.total_processed + "</p>";
                        html += "<p><strong>Réussis:</strong> " + status.final_summary.successful + "</p>";
                        html += "<p><strong>Échoués:</strong> " + status.final_summary.failed + "</p>";
                        html += "<p><strong>Taux de réussite:</strong> " + status.final_summary.success_rate + "%</p>";
                        html += "</div>";
                    }
                    
                    html += "</div>";
                    container.innerHTML = html;
                }

                function showError(message) {
                    const container = document.getElementById("statusContent");
                    container.innerHTML = "<div class=\"status-failed\"><h4>❌ Erreur</h4><p>" + message + "</p></div>";
                }

                function getStatusText(status) {
                    const statusMap = {
                        "started": "🟡 Démarré",
                        "fetching_forms": "🔍 Récupération des formulaires",
                        "processing": "⚙️ En cours de traitement",
                        "completed": "✅ Terminé avec succès",
                        "failed": "❌ Échec"
                    };
                    return statusMap[status] || status;
                }

                window.addEventListener("beforeunload", function() {
                    if (statusInterval) {
                        clearInterval(statusInterval);
                    }
                });
            </script>
        </body>
        </html>';

        return new Response($html);
    }
    
    // ------------------------------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------------------------------

    /**
     * 
     * Save PDF etat des lieux portails on remote server
     */
    #[Route('/api/forms/save/etatdeslieuxportails/pdf', name: 'app_api_form_save_etatdeslieuxportails_pdf', methods: ['GET'])]
    public function savePortailsPdfInPublicFolder(FormRepository $formRepository): JsonResponse
    {
        //Changer l'appel à la fonction saveEquipementPdfInPublicFolder() pour enregistrer les PDF standard des état des lieux portail
        $formRepository->savePortailsPdfInPublicFolder();
        
        return new JsonResponse("Les pdf d'état des lieux portails ont bien été sauvegardés ", Response::HTTP_OK, [], true);
    }

    /**
     * Function to SAVE new equipments from technicians forms MAINTENANCE from formulaires Visite maintenance To local BDD
     * then call route to save portails at  #[Route('/api/forms/update/portails', name: 'app_api_form_update_portails', methods: ['GET'])]
     * --------------- OK POUR TOUTES LES AGENCES DE S10 à S170
     */
    #[Route('/api/forms/update/maintenance', name: 'app_api_form_update', methods: ['GET'])]
    public function getDataOfFormsMaintenance(FormRepository $formRepository,EntityManagerInterface $entityManager, CacheInterface $cache)
        {
        $entiteEquipementS10 = new EquipementS10;
        $entiteEquipementS40 = new EquipementS40;
        $entiteEquipementS50 = new EquipementS50;
        $entiteEquipementS60 = new EquipementS60;
        $entiteEquipementS70 = new EquipementS70;
        $entiteEquipementS80 = new EquipementS80;
        $entiteEquipementS100 = new EquipementS100;
        $entiteEquipementS120 = new EquipementS120;
        $entiteEquipementS130 = new EquipementS130;
        $entiteEquipementS140 = new EquipementS140;
        $entiteEquipementS150 = new EquipementS150;
        $entiteEquipementS160 = new EquipementS160;
        $entiteEquipementS170 = new EquipementS170;
        
        
        // GET all technicians forms formulaire Visite maintenance
        $dataOfFormMaintenance  =  $formRepository->getDataOfFormsMaintenance($cache);
        
        // --------------------------------------                       Call function iterate by list equipments -------------------------------------------
        $allResumesGroupEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS10::class)->findAll());
        $allResumesStEtienneEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS40::class)->findAll());
        $allResumesGrenobleEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS50::class)->findAll());
        $allResumesLyonEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS60::class)->findAll());
        $allResumesBordeauxEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS70::class)->findAll());
        $allResumesParisNordEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS80::class)->findAll());
        $allResumesMontpellierEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS100::class)->findAll());
        $allResumesHautsDeFranceEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS120::class)->findAll());
        $allResumesToulouseEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS130::class)->findAll());
        $allResumesSmpEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS140::class)->findAll());
        $allResumesSogefiEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS150::class)->findAll());
        $allResumesRouenEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS160::class)->findAll());
        // $allResumesRennesEquipementsInDatabase = $formRepository->iterateListEquipementsToGetResumes($entityManager->getRepository(EquipementS170::class)->findAll());
        
        foreach ($dataOfFormMaintenance as $equipements){
            
            // ----------------------------------------------------------   
            // IF code_agence d'$equipements = S50 ou S100 ou etc... on boucle sur ses équipements supplémentaires
            // ----------------------------------------------------------
            switch ($equipements['code_agence']['value']) {
                // Passer à la fonction createAndSaveInDatabaseByAgency()
                // les variables $equipements avec les nouveaux équipements des formulaires de maintenance, le tableau des résumés de l'agence et son entité ex: $entiteEquipementS10
                case 'S10':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesGroupEquipementsInDatabase, $entiteEquipementS10, $entityManager);
                    break;
                
                case 'S40':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesStEtienneEquipementsInDatabase, $entiteEquipementS40, $entityManager);
                    break;
                
                case 'S50':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesGrenobleEquipementsInDatabase, $entiteEquipementS50, $entityManager);
                    break;
                
                
                case 'S60':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesLyonEquipementsInDatabase, $entiteEquipementS60, $entityManager);
                    break;
                
                
                case 'S70':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesBordeauxEquipementsInDatabase, $entiteEquipementS70, $entityManager);
                    break;
                
                
                case 'S80':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesParisNordEquipementsInDatabase, $entiteEquipementS80, $entityManager);
                    break;
                
                
                case 'S100':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesMontpellierEquipementsInDatabase, $entiteEquipementS100, $entityManager);
                    break;
                
                
                case 'S120':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesHautsDeFranceEquipementsInDatabase, $entiteEquipementS120, $entityManager);
                    break;
                
                
                case 'S130':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesToulouseEquipementsInDatabase, $entiteEquipementS130, $entityManager);
                    break;
                
                
                case 'S140':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesSmpEquipementsInDatabase, $entiteEquipementS140, $entityManager);
                    break;
                
                
                case 'S150':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesSogefiEquipementsInDatabase, $entiteEquipementS150, $entityManager);
                    break;
                
                
                case 'S160':
                    $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesRouenEquipementsInDatabase, $entiteEquipementS160, $entityManager);
                    break;
                
                
                // case 'S170':
                //     $formRepository->createAndSaveInDatabaseByAgency($equipements, $allResumesRennesEquipementsInDatabase, $entiteEquipementS170, $entityManager);
                //     break;
                
                default:
                    break;
            }
            
        }
        return new JsonResponse("Les équipements en maintenance sont bien enregistrés en base !", Response::HTTP_OK, [], true);
        // return $this->redirectToRoute('app_api_form_update_portails');
    }

    /**
     * Function to ADD new PORTAILS from technicians forms in BDD and save PDF état des lieux locally
     */
    #[Route('/api/forms/update/portails', name: 'app_api_form_update_portails', methods: ['GET'])]
    public function getEtatDesLieuxPortailsDataOfForms(FormRepository $formRepository, EntityManagerInterface $entityManager)
    {

        $entiteEquipementS10 = new EquipementS10;
        $entiteEquipementS40 = new EquipementS40;
        $entiteEquipementS50 = new EquipementS50;
        $entiteEquipementS60 = new EquipementS60;
        $entiteEquipementS70 = new EquipementS70;
        $entiteEquipementS80 = new EquipementS80;
        $entiteEquipementS100 = new EquipementS100;
        $entiteEquipementS120 = new EquipementS120;
        $entiteEquipementS130 = new EquipementS130;
        $entiteEquipementS140 = new EquipementS140;
        $entiteEquipementS150 = new EquipementS150;
        $entiteEquipementS160 = new EquipementS160;
        $entiteEquipementS170 = new EquipementS170;
        
        // -------------------------------------------
        // ----------------Call function iterate by list equipments to get ONLY Portails in Equipement_numeroAgence list IN LOCAL BDD
        // --------------  OK for this function
        // -------------------------------------------
        
        $allGroupPortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS10::class)->findAll());
        $allStEtiennePortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS40::class)->findAll());
        $allGrenoblePortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS50::class)->findAll());
        $allLyonPortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS60::class)->findAll());
        $allBordeauxPortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS70::class)->findAll());
        $allParisNordPortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS80::class)->findAll());
        $allMontpellierPortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS100::class)->findAll());
        $allHautsDeFrancePortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS120::class)->findAll());
        $allToulousePortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS130::class)->findAll());
        $allSmpPortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS140::class)->findAll());
        $allSogefiPortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS150::class)->findAll());
        $allRouenPortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS160::class)->findAll());
        $allRennesPortailsInDatabase = $formRepository->getOneTypeOfEquipementInListEquipements("portail", $entityManager->getRepository(EquipementS170::class)->findAll());

        
        // -------------------------------------------
        // --------------------------------------     Call function iterate by list of portails to get resumes in if_exist_db 
        // ----------------------------------  OK for this function
        // -------------------------------------------
        $allResumesGroupPortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allGroupPortailsInDatabase);
        $allResumesStEtiennePortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes( $allStEtiennePortailsInDatabase);
        $allResumesGrenoblePortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allGrenoblePortailsInDatabase);
        $allResumesLyonPortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allLyonPortailsInDatabase);
        $allResumesBordeauxPortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allBordeauxPortailsInDatabase);
        $allResumesParisNordPortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allParisNordPortailsInDatabase);
        $allResumesMontpellierPortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allMontpellierPortailsInDatabase);
        $allResumesHautsDeFrancePortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allHautsDeFrancePortailsInDatabase);
        $allResumesToulousePortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allToulousePortailsInDatabase);
        $allResumesSmpPortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allSmpPortailsInDatabase);
        $allResumesSogefiPortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allSogefiPortailsInDatabase);
        $allResumesRouenPortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allRouenPortailsInDatabase);
        $allResumesRennesPortailsInDatabase = $formRepository->iterateListEquipementsToGetResumes($allRennesPortailsInDatabase);
        
        // GET all technicians Etat des lieux portails forms from list class PORTAILS
        $dataOfFormsEtatDesLieuxPortails  =  $formRepository->getEtatDesLieuxPortailsDataOfForms();

        foreach ($dataOfFormsEtatDesLieuxPortails as $formPortail) { 
            
            /**
            * Persist each portail in database
            */
            if (isset($formPortail['data']['fields']['n_agence']['value'])) {
                # code...
                switch($formPortail['data']['fields']['n_agence']['value']){
                    case 'S10':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesGroupPortailsInDatabase, $entiteEquipementS10, $entityManager);
                        break;
                    
                    case 'S40':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesStEtiennePortailsInDatabase, $entiteEquipementS40, $entityManager);
                        break;
                    
                    case 'S50':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesGrenoblePortailsInDatabase, $entiteEquipementS50, $entityManager);
                        break;
                    
                    
                    case 'S60':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesLyonPortailsInDatabase, $entiteEquipementS60, $entityManager);
                        break;
                    
                    
                    case 'S70':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesBordeauxPortailsInDatabase, $entiteEquipementS70, $entityManager);
                        break;
                    
                    
                    case 'S80':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesParisNordPortailsInDatabase, $entiteEquipementS80, $entityManager);
                        break;
                    
                    
                    case 'S100':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesMontpellierPortailsInDatabase, $entiteEquipementS100, $entityManager);
                        break;
                    
                    
                    case 'S120':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesHautsDeFrancePortailsInDatabase, $entiteEquipementS120, $entityManager);
                        break;
                    
                    
                    case 'S130':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesToulousePortailsInDatabase, $entiteEquipementS130, $entityManager);
                        break;
                    
                    
                    case 'S140':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesSmpPortailsInDatabase, $entiteEquipementS140, $entityManager);
                        break;
                    
                    
                    case 'S150':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesSogefiPortailsInDatabase, $entiteEquipementS150, $entityManager);
                        break;
                    
                    
                    case 'S160':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesRouenPortailsInDatabase, $entiteEquipementS160, $entityManager);
                        break;
                    
                    
                    case 'S170':
                        $formRepository->saveNewPortailsInDatabaseByAgency("portail", $formPortail, $allResumesRennesPortailsInDatabase, $entiteEquipementS170, $entityManager);
                        break;
                    
                    default:
                        break;
                }
            }
        }
        
        return "Les portails d'états des lieux portails sont bien enregistrés en base !";
        // return $this->redirectToRoute('app_api_form_update_lists_equipements');
    }

    /**
     * UPDATE LIST OF PORTAILS ON KIZEO AND FLUSH NEW PORTAILS IN LOCAL DATABASE    --------------- OK POUR TOUTES LES AGENCES DE S10 à S170
     * 
     */
    // #[Route('/api/forms/update/lists/portails', name: 'app_api_form_update_lists_portails', methods: ['GET','PUT'])]
    // public function putUpdatesListsPortailsFromKizeoForms(FormRepository $formRepository){
    //     $dataOfFormListPortails  =  $formRepository->getEtatDesLieuxPortailsDataOfForms();
    //     dd($dataOfFormListPortails);
    //     // GET portails des agences de Grenoble, Paris et Montpellier en apellant la fonction getAgencyListEquipementsFromKizeoByListId($list_id) avec leur ID de list sur KIZEO
    //     // $portailsGroup = $formRepository->getAgencyListPortailsFromKizeoByListId();
    //     // $portailsStEtienne = $formRepository->getAgencyListPortailsFromKizeoByListId(418520);
    //     $portailsGrenoble = $formRepository->getAgencyListPortailsFromKizeoByListId(418507);
    //     // $portailsLyon = $formRepository->getAgencyListPortailsFromKizeoByListId(418519);
    //     // $portailsBordeaux = $formRepository->getAgencyListPortailsFromKizeoByListId(419394);
    //     $portailsParis = $formRepository->getAgencyListPortailsFromKizeoByListId(417773);
    //     $portailsMontpellier = $formRepository->getAgencyListPortailsFromKizeoByListId(419710);
    //     // $portailsHautsDeFrance = $formRepository->getAgencyListPortailsFromKizeoByListId(417950);
    //     // $portailsToulouse = $formRepository->getAgencyListPortailsFromKizeoByListId(419424);
    //     // $portailsSmp = $formRepository->getAgencyListPortailsFromKizeoByListId();
    //     // $portailsSogefi = $formRepository->getAgencyListPortailsFromKizeoByListId(422539);
    //     // $portailsRouen = $formRepository->getAgencyListPortailsFromKizeoByListId();
    //     // $portailsRennes = $formRepository->getAgencyListPortailsFromKizeoByListId();
        
    //     foreach($dataOfFormListPortails as $key=>$value){

    //         switch ($dataOfFormListPortails[$key]['code_agence']['value']) {
    //             // Fonction uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails,$key,$agencyEquipments,$agencyListId)
    //             // case 'S10':
    //             //     $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsGroup, );
    //             //     dump('Uploads S10 OK');
    //             //     break;
    //             // case 'S40':
    //             //     $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsStEtienne, );
    //             //     dump('Uploads S40 OK');
    //             //     break;
    //             case 'S50':
    //                 $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsGrenoble, 414025);
    //                 dump('Uploads S50 OK');
    //                 break;
    //             // case 'S60':
    //             //     $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsLyon, );
    //             //     dump('Uploads S60 OK');
    //             //     break;
    //             // case 'S70':
    //             //     $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsBordeaux, );
    //             //     dump('Uploads S70 OK');
    //             //     break;
                
    //             case 'S80':
    //                 $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsParis, 421993);
    //                 dump('Uploads for S80 OK');
    //                 break;
                
    //             case 'S100':
    //                 $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsMontpellier, 423853);
    //                 dump('Uploads for S100 OK');
    //                 break;
                
    //             // case 'S120':
    //             //     $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsHautsDeFrance, );
    //             //     dump('Uploads for S120 OK');
    //             //     break;
                
    //             // case 'S130':
    //             //     $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsToulouse, );
    //             //     dump('Uploads for S130 OK');
    //             //     break;
                
    //             // case 'S140':
    //             //     $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsSmp, );
    //             //     dump('Uploads for S140 OK');
    //             //     break;
                
    //             // case 'S150':
    //             //     $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsSogefi, );
    //             //     dump('Uploads for S150 OK');
    //             //     break;
                
    //             // case 'S160':
    //             //     $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsRouen, );
    //             //     dump('Uploads for S160 OK');
    //             //     break;
                
    //             // case 'S170':
    //             //     $formRepository->uploadListAgencyWithNewRecordsOnKizeo($dataOfFormListPortails, $key, $portailsRennes, );
    //             //     dump('Uploads for S170 OK');
    //             //     break;
                
    //             default:
    //                 return new JsonResponse('this is not for our agencies', Response::HTTP_OK, [], true);
    //                 break;
    //         }
    //     }

    //     // ----------------------                 Save new portails in database from all agencies
        

    //     // return new JsonResponse('La mise à jour sur KIZEO s\'est bien déroulée !', Response::HTTP_OK, [], true);
    //     return $this->redirectToRoute('app_api_form_update_portails');
    // }
    
}
