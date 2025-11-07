<?php
// src/Controller/EquipementPdfController.php
namespace App\Controller;

use App\Entity\Form;
use App\Entity\ContactS10;
use App\Entity\ContactS40;
use App\Entity\ContactS50;
use App\Entity\ContactS60;
use App\Entity\ContactS70;
use App\Entity\ContactS80;
use App\Entity\ContactS100;
use App\Entity\ContactS120;
use App\Entity\ContactS130;
use App\Entity\ContactS140;
use App\Entity\ContactS150;
use App\Entity\ContactS160;
use App\Entity\ContactS170;
use App\Entity\EquipementS10;
use App\Entity\EquipementS40;
use App\Entity\EquipementS50;
use App\Entity\EquipementS60;
use App\Entity\EquipementS70;
use App\Entity\EquipementS80;
use App\Service\EmailService;
use App\Service\PdfGenerator;
use App\Entity\EquipementS100;
use App\Entity\EquipementS120;
use App\Entity\EquipementS130;
use App\Entity\EquipementS140;
use App\Entity\EquipementS150;
use App\Entity\EquipementS160;
use App\Entity\EquipementS170;
use App\Repository\FormRepository;
use App\Service\ShortLinkService;
use App\Service\PdfStorageService;
use App\Service\ImageStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class EquipementPdfController extends AbstractController
{
    private $pdfGenerator;
    private $imageStorageService;
    private PdfStorageService $pdfStorageService;
    private ShortLinkService $shortLinkService;
    private EmailService $emailService;
    
    public function __construct(PdfGenerator $pdfGenerator, ImageStorageService $imageStorageService, PdfStorageService $pdfStorageService, ShortLinkService $shortLinkService, EmailService $emailService)
    {
        $this->pdfGenerator = $pdfGenerator;
        $this->imageStorageService = $imageStorageService;
        $this->pdfStorageService = $pdfStorageService;
        $this->shortLinkService = $shortLinkService;
        $this->emailService = $emailService;
    }
    
    /**
     * 
     */
    #[Route('/equipement/pdf/{agence}/{id}', name: 'equipement_pdf_single')]
    public function generateSingleEquipementPdf(string $agence, string $id, EntityManagerInterface $entityManager): Response
    {
        // Récupérer l'équipement selon l'agence (même logique que votre fonction existante)
        $equipment = $this->getEquipmentByAgence($agence, $id, $entityManager);
        
        if (!$equipment) {
            throw $this->createNotFoundException('Équipement non trouvé');
        }
        
        // Récupérer les photos selon votre logique existante
        $picturesArray = $entityManager->getRepository(Form::class)->findBy([
            'code_equipement' => $equipment->getNumeroEquipement(), 
            'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()
        ]);
        
        $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
        
        // Générer le HTML pour le PDF
        $html = $this->renderView('pdf/single_equipement.html.twig', [
            'equipment' => $equipment,
            'picturesData' => $picturesData,
            'agence' => $agence
        ]);
        
        // Générer le PDF
        $filename = "equipement_{$equipment->getNumeroEquipement()}_{$agence}.pdf";
        $pdfContent = $this->pdfGenerator->generatePdf($html, $filename);
        
        // Retourner le PDF
        return new Response(
            $pdfContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"$filename\""
            ]
        );
    }
    
    /**
     * Nettoie les valeurs qui commencent par "A COMPLETER", "A RENSEIGNER", etc.
     * Version avancée qui gère différents formats et accents
     */
    private function cleanCompleteValuesAdvanced(string $value): string
    {
        // Patterns possibles : "A COMPLETER", "A COMPLETERgirardo", "A COMPLETER girardo", etc.
        $patterns = [
            '/^A\s*COMPLETER\s*-?\s*/i',  // "A COMPLETER" suivi optionnellement d'un tiret
            '/^A\s*RENSEIGNER\s*-?\s*/i', // "A RENSEIGNER" aussi
            '/^À\s*COMPLETER\s*-?\s*/i',  // Avec accent
            '/^À\s*RENSEIGNER\s*-?\s*/i'  // Avec accent
        ];
        
        $cleanedValue = trim($value);
        
        foreach ($patterns as $pattern) {
            $cleanedValue = preg_replace($pattern, '', $cleanedValue);
        }
        
        return trim($cleanedValue);
    }

    /**
     * Applique le nettoyage "A COMPLETER" à un équipement
     */
    private function cleanEquipmentValues($equipment): void
    {
        // Liste des méthodes getter/setter à nettoyer
        $fieldsToClean = [
            'Marque' => ['getMarque', 'setMarque'],
            'MiseEnService' => ['getMiseEnService', 'setMiseEnService'],
            'LibelleEquipement' => ['getLibelleEquipement', 'setLibelleEquipement'],
            'NumeroDeSerie' => ['getNumeroDeSerie', 'setNumeroDeSerie'],
            'Hauteur' => ['getHauteur', 'setHauteur'],
            'Largeur' => ['getLargeur', 'setLargeur'],
            'Longueur' => ['getLongueur', 'setLongueur'],
            'RepereSiteClient' => ['getRepereSiteClient', 'setRepereSiteClient'],
            'ModeFonctionnement' => ['getModeFonctionnement', 'setModeFonctionnement'],
            'PlaqueSignaletique' => ['getPlaqueSignaletique', 'setPlaqueSignaletique'],
            'Etat' => ['getEtat', 'setEtat'],
            'RaisonSociale' => ['getRaisonSociale', 'setRaisonSociale'],
            'Modele' => ['getModele', 'setModele']
        ];
        
        foreach ($fieldsToClean as $fieldName => [$getter, $setter]) {
            try {
                // Vérifier que les méthodes existent avant de les appeler
                if (method_exists($equipment, $getter) && method_exists($equipment, $setter)) {
                    $currentValue = $equipment->$getter();
                    
                    if (is_string($currentValue) && !empty($currentValue)) {
                        $cleanedValue = $this->cleanCompleteValuesAdvanced($currentValue);
                        
                        // Seulement modifier si la valeur a changé
                        if ($cleanedValue !== $currentValue) {
                            $this->customLog("Nettoyage {$fieldName}: '{$currentValue}' -> '{$cleanedValue}'");
                            $equipment->$setter($cleanedValue);
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->customLog("Erreur nettoyage champ {$fieldName}: " . $e->getMessage());
            }
        }
    }

    /**
     * FONCTION PRINCIPALE MODIFIÉE - generateClientEquipementsPdf
     * VERSION OPTIMISÉE avec nettoyage des valeurs "A COMPLETER"
     */
    #[Route('/client/equipements/pdf/{agence}/{id}', name: 'client_equipements_pdf')]
    public function generateClientEquipementsPdf(Request $request, string $agence, string $id, EntityManagerInterface $entityManager): Response
    {
        // CONFIGURATION MÉMOIRE ET TEMPS D'EXÉCUTION OPTIMISÉE
        ini_set('memory_limit', '1G'); // 1 Go
        ini_set('max_execution_time', 600); // 10 minutes au lieu de 300 secondes avant
        set_time_limit(600); // 10 minutes au lieu de 300 secondes avant
        
        // Activer le garbage collector agressif
        gc_enable();
        
        $startMemory = memory_get_usage(true);
        $this->customLog("Mémoire initiale: " . $this->formatBytes($startMemory));

        // 1. TOUJOURS initialiser imageUrl dès le début
        $imageUrl = $this->getImageUrlForAgency($agence) ?: 'https://www.pdf.somafi-group.fr/background/group.jpg';
        
        // Initialiser les métriques de performance
        $startTime = microtime(true);
        $photoSourceStats = ['direct_scan' => 0, 'local' => 0, 'api_fallback' => 0, 'none' => 0, 'error' => 0];
        
        try {
            // Configuration MySQL optimisée pour les gros volumes
            $entityManager->getConnection()->executeStatement('SET SESSION wait_timeout = 300');
            $entityManager->getConnection()->executeStatement('SET SESSION interactive_timeout = 300');
            
            // Récupérer les filtres depuis les paramètres de la requête
            $clientAnneeFilter = $request->query->get('clientAnneeFilter', '');
            $clientVisiteFilter = $request->query->get('clientVisiteFilter', '');
            $withPhotos = $request->query->get('withPhotos', 'non');
            
            $maxEquipments = (int) $request->query->get('maxEquipments', 500);
            
            $this->customLog("=== GÉNÉRATION PDF CLIENT ===");
            $this->customLog("Avec ou sans photo: {$withPhotos}");
            $this->customLog("Agence: {$agence}, Client: {$id}");
            $this->customLog("Filtres - Année: '{$clientAnneeFilter}', Visite: '{$clientVisiteFilter}'");
            $this->customLog("Limite d'équipements: {$maxEquipments}");
            
            // Récupérer les informations client TOUT DE SUITE
            $clientSelectedInformations = $entityManager->getRepository("App\\Entity\\Contact{$agence}")->findOneBy(['id_contact' => $id]);
            
            // Récupérer les informations client (autre méthode)
            $clientInfo = $this->getClientInfo($agence, $id, $entityManager);
            $this->customLog("Client info récupérées: " . json_encode($clientInfo));
            
            // 2. RÉCUPÉRATION SIMPLIFIÉE ET SÉCURISÉE DES ÉQUIPEMENTS
            $equipments = $this->getEquipmentsByClientAndAgence($agence, $id, $entityManager);
            $this->customLog("Équipements bruts trouvés: " . count($equipments));
            
            if (empty($equipments)) {
                throw new \Exception("Aucun équipement trouvé pour le client {$id}");
            }
            
            // ✅ NOUVEAU : NETTOYAGE DES VALEURS "A COMPLETER" SUR TOUS LES ÉQUIPEMENTS
            $this->customLog("=== DÉBUT NETTOYAGE DES VALEURS 'A COMPLETER' ===");
            $cleanedCount = 0;
            
            foreach ($equipments as $equipment) {
                try {
                    $this->cleanEquipmentValues($equipment);
                    $cleanedCount++;
                } catch (\Exception $e) {
                    $this->customLog("Erreur nettoyage équipement {$equipment->getNumeroEquipement()}: " . $e->getMessage());
                }
            }
            
            $this->customLog("Nettoyage terminé sur {$cleanedCount} équipements");
            $this->customLog("=== FIN NETTOYAGE DES VALEURS 'A COMPLETER' ===");
            
            // 3. LOGIQUE DE FILTRAGE CORRIGÉE SELON VOS SPÉCIFICATIONS
            $equipmentsFiltered = [];
            $filtreApplique = false;
            
            if (!empty($clientAnneeFilter) || !empty($clientVisiteFilter)) {
                // CAS AVEC FILTRES : équipements de la visite sélectionnée avec année de dernière visite
                $this->customLog("Application des filtres spécifiques...");
                
                foreach ($equipments as $equipment) {
                    try {
                        $matches = true;
                        
                        // Filtre par visite si défini
                        if (!empty($clientVisiteFilter)) {
                            $visiteEquipment = $equipment->getVisite();
                            if ($visiteEquipment !== $clientVisiteFilter) {
                                $matches = false;
                            }
                            $this->customLog("Équipement {$equipment->getNumeroEquipement()}: visite '{$visiteEquipment}' vs filtre '{$clientVisiteFilter}' = " . ($matches ? 'OUI' : 'NON'));
                        }
                        
                        // Filtre par année de dernière visite si défini
                        if ($matches && !empty($clientAnneeFilter)) {
                            $derniereVisite = $equipment->getDerniereVisite();
                            if ($derniereVisite) {
                                $anneeEquipment = date("Y", strtotime($derniereVisite));
                                if ($anneeEquipment !== $clientAnneeFilter) {
                                    $matches = false;
                                }
                                $this->customLog("Équipement {$equipment->getNumeroEquipement()}: année dernière visite {$anneeEquipment} vs filtre {$clientAnneeFilter} = " . ($matches ? 'OUI' : 'NON'));
                            } else {
                                $matches = false;
                                $this->customLog("Équipement {$equipment->getNumeroEquipement()}: pas de date de dernière visite");
                            }
                        }
                        
                        if ($matches) {
                            $equipmentsFiltered[] = $equipment;
                            $filtreApplique = true;
                        }
                        
                    } catch (\Exception $e) {
                        $this->customLog("Erreur filtrage équipement {$equipment->getNumeroEquipement()}: " . $e->getMessage());
                    }
                }
                
                $this->customLog("Après filtrage: " . count($equipmentsFiltered) . " équipements");
                
            } else {
                // CAS PAR DÉFAUT : équipements de la dernière visite uniquement
                $this->customLog("Pas de filtres - récupération équipements de la dernière visite");
                
                // Trouver la date de dernière visite la plus récente
                $derniereVisiteMax = null;
                foreach ($equipments as $equipment) {
                    $derniereVisite = $equipment->getDerniereVisite();
                    if ($derniereVisite && (!$derniereVisiteMax || strtotime($derniereVisite) > strtotime($derniereVisiteMax))) {
                        $derniereVisiteMax = $derniereVisite;
                    }
                }
                
                if ($derniereVisiteMax) {
                    $anneeDerniereVisite = date("Y", strtotime($derniereVisiteMax));
                    $this->customLog("Dernière visite trouvée: {$derniereVisiteMax} (année: {$anneeDerniereVisite})");
                    
                    // Filtrer les équipements de cette dernière visite (même année)
                    foreach ($equipments as $equipment) {
                        $derniereVisite = $equipment->getDerniereVisite();
                        if ($derniereVisite && date("Y", strtotime($derniereVisite)) === $anneeDerniereVisite) {
                            $equipmentsFiltered[] = $equipment;
                        }
                    }
                } else {
                    // Fallback : tous les équipements si aucune date trouvée
                    $this->customLog("Aucune date de dernière visite trouvée - utilisation de tous les équipements");
                    $equipmentsFiltered = $equipments;
                }
            }
            
            // 4. LIMITATION CRITIQUE : Ne traiter que les X premiers équipements
            if (count($equipmentsFiltered) > $maxEquipments) {
                $this->customLog("LIMITATION: Réduction de " . count($equipmentsFiltered) . " à {$maxEquipments} équipements");
                $equipmentsFiltered = array_slice($equipmentsFiltered, 0, $maxEquipments);
            }
            
            // 5. VÉRIFICATION APRÈS FILTRAGE
            if (empty($equipmentsFiltered)) {
                $this->customLog("ATTENTION: Aucun équipement après filtrage!");
                
                // Debug des équipements disponibles
                $sampleEquipments = array_slice($equipments, 0, 5);
                foreach ($sampleEquipments as $eq) {
                    $this->customLog("Équipement échantillon - Num: {$eq->getNumeroEquipement()}, Visite: '{$eq->getVisite()}', Dernière visite: {$eq->getDerniereVisite()}");
                }
                
                // Générer un PDF d'erreur informatif
                return $this->generateErrorPdf($agence, $id, $imageUrl, $entityManager, 
                    "Aucun équipement ne correspond aux filtres sélectionnés.", 
                    [
                        'filtre_annee' => $clientAnneeFilter,
                        'filtre_visite' => $clientVisiteFilter,
                        'total' => count($equipments)
                    ]
                );
            }
            
            // 6. TRAITEMENT DES ÉQUIPEMENTS AVEC PHOTOS - VERSION SCAN DYNAMIQUE
            $equipmentsWithPictures = [];
            $dateDeDerniererVisite = null;
            $processedCount = 0;
            $formRepository = $entityManager->getRepository(Form::class);
            
            foreach ($equipmentsFiltered as $index => $equipment) {
                try {
                    $this->customLog("=== DÉBUT TRAITEMENT ÉQUIPEMENT {$index} ===");
                    
                    // Garbage collection plus fréquent
                    if ($index > 0 && $index % 20 === 0) {
                        gc_collect_cycles();
                        $currentMemory = memory_get_usage(true);
                        if ($currentMemory > 0) {
                            $this->customLog("GC forcé #{$index} - Mémoire: " . $this->formatBytes($currentMemory));
                        }
                    }

                    // PROTECTION contre les équipements avec numéro vide
                    $numeroEquipement = $equipment->getNumeroEquipement();
                    if (empty($numeroEquipement)) {
                        $this->customLog("ATTENTION: Équipement avec numéro vide trouvé (ID: {$equipment->getId()})");
                        continue; // Ignorer cet équipement
                    }
                    
                    $this->customLog("Traitement équipement: {$numeroEquipement}");

                    // Vérification isEnMaintenance
                    $isInMaintenance = false;
                    if (method_exists($equipment, 'isEnMaintenance')) {
                        try {
                            $isInMaintenance = $equipment->isEnMaintenance();
                            $this->customLog("isEnMaintenance: " . ($isInMaintenance ? 'true' : 'false'));
                        } catch (\Exception $e) {
                            $this->customLog("Erreur isEnMaintenance: " . $e->getMessage());
                        }
                    }
                    
                    // Récupération raison sociale et visite
                    try {
                        $raisonSociale = $equipment->getRaisonSociale();
                        $visite = $equipment->getVisite();
                        $this->customLog("Raison sociale: " . substr($raisonSociale, 0, 50) . "...");
                        $this->customLog("Visite: {$visite}");
                    } catch (\Exception $e) {
                        $this->customLog("Erreur récupération données base: " . $e->getMessage());
                        continue;
                    }

                    // ✅ ============================================= NOUVELLE RÉCUPÉRATION DES PHOTOS AVEC SCAN DYNAMIQUE
                    $picturesData = [];
                    try {
                        $this->customLog("🔍 Tentative scan dynamique pour {$numeroEquipement}");
                        
                        // Utiliser la nouvelle fonction de scan dynamique
                        $scanResult = $this->getPhotosForEquipmentOptimized($equipment, $agence);
                        
                        if (!empty($scanResult['photos'])) {
                            // Adapter le format pour compatibilité avec le template
                            $picturesData = $scanResult['photos'] ?? [];
                            $photoSourceStats['direct_scan']++;
                            $this->customLog("✅ Photos trouvées via scan dynamique: " . count($picturesData));
                            $this->customLog("Dossier client détecté: " . ($scanResult['client_folder_found'] ?? 'N/A'));
                        } else {
                            $this->customLog("❌ Aucune photo trouvée via scan dynamique");
                            
                            // Fallback uniquement pour les équipements en maintenance
                            if ($isInMaintenance) {
                                $this->customLog("Tentative fallback API pour équipement en maintenance...");
                                $picturesArray = $entityManager->getRepository(Form::class)->findBy([
                                    'code_equipement' => $numeroEquipement,
                                    'raison_sociale_visite' => $raisonSociale . "\\" . $visite
                                ]);
                                
                                if (!empty($picturesArray)) {
                                    $picturesData = $entityManager->getRepository(Form::class)
                                        ->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                                    if (!empty($picturesData)) {
                                        $photoSourceStats['api_fallback']++;
                                        $this->customLog("Photos API récupérées: " . count($picturesData));
                                    } else {
                                        $photoSourceStats['none']++;
                                    }
                                } else {
                                    $photoSourceStats['none']++;
                                }
                            } else {
                                $photoSourceStats['none']++;
                            }
                        }
                    } catch (\Exception $e) {
                        $this->customLog("ERREUR lors de la récupération des photos: " . $e->getMessage());
                        $picturesData = [];
                        $photoSourceStats['error']++;
                    }

                    // Construction des données d'équipement
                    try {
                        $equipmentData = [
                            'equipment' => $equipment,
                            'pictures' => $picturesData,
                            'numeroEquipement' => $numeroEquipement,
                            'isEnMaintenance' => $isInMaintenance
                        ];
                        
                        $equipmentsWithPictures[] = $equipmentData;
                        $processedCount++;
                        
                        $this->customLog("Équipement ajouté avec succès. Total traités: {$processedCount}");
                        
                    } catch (\Exception $e) {
                        $this->customLog("Erreur construction données équipement: " . $e->getMessage());
                    }

                    $this->customLog("=== FIN TRAITEMENT ÉQUIPEMENT {$index} ===");

                    // CONTRÔLE MÉMOIRE CRITIQUE
                    $currentMemoryAfter = memory_get_usage(true);
                    if ($currentMemoryAfter > 400 * 1024 * 1024) { // 400 MB
                        $this->customLog("ATTENTION: Mémoire critique après équipement {$numeroEquipement}: " . 
                                        $this->formatBytes($currentMemoryAfter));
                        
                        $this->customLog("Arrêt anticipé pour éviter OutOfMemory.");
                        break;
                    }
                    
                } catch (\Exception $e) {
                    $this->customLog("EXCEPTION dans boucle équipement {$index}: " . $e->getMessage());
                    $photoSourceStats['error']++;
                    continue; // Continuer avec l'équipement suivant
                }
            }
            
            // DÉDUPLICATION DES ÉQUIPEMENTS PAR NUMÉRO ET DATE DE VISITE
            $this->customLog("=== DÉBUT DÉDUPLICATION ===");
            $this->customLog("Nombre d'équipements avant déduplication: " . count($equipmentsWithPictures));

            $uniqueEquipments = [];
            $duplicatesRemoved = 0;

            foreach ($equipmentsWithPictures as $equipmentData) {
                $numeroEquipement = $equipmentData['numeroEquipement'];
                $equipment = $equipmentData['equipment'];
                
                try {
                    // Récupération de la date de dernière visite
                    $dateVisite = null;
                    if (method_exists($equipment, 'getDerniereVisite')) {
                        $derniereVisite = $equipment->getDerniereVisite();
                        if ($derniereVisite instanceof \DateTime) {
                            $dateVisite = $derniereVisite;
                        } elseif (is_string($derniereVisite) && !empty($derniereVisite)) {
                            try {
                                $dateVisite = new \DateTime($derniereVisite);
                            } catch (\Exception $e) {
                                $this->customLog("Impossible de parser la date de dernière visite: {$derniereVisite}");
                                $dateVisite = new \DateTime('1970-01-01'); // Date par défaut très ancienne
                            }
                        }
                    }
                    
                    // Si aucune date trouvée, utiliser une date par défaut très ancienne
                    if (!$dateVisite) {
                        $dateVisite = new \DateTime('1970-01-01');
                    }
                    
                    // Vérifier si cet équipement existe déjà
                    if (!isset($uniqueEquipments[$numeroEquipement])) {
                        // Premier équipement avec ce numéro
                        $uniqueEquipments[$numeroEquipement] = [
                            'data' => $equipmentData,
                            'dateVisite' => $dateVisite
                        ];
                        $this->customLog("Nouvel équipement: {$numeroEquipement} - Date: " . $dateVisite->format('Y-m-d H:i:s'));
                    } else {
                        // Équipement déjà existant, comparer les dates
                        $existingDate = $uniqueEquipments[$numeroEquipement]['dateVisite'];
                        
                        if ($dateVisite > $existingDate) {
                            // L'équipement actuel est plus récent
                            $this->customLog("Remplacement équipement {$numeroEquipement}: " . 
                                        $existingDate->format('Y-m-d H:i:s') . " -> " . $dateVisite->format('Y-m-d H:i:s'));
                            $uniqueEquipments[$numeroEquipement] = [
                                'data' => $equipmentData,
                                'dateVisite' => $dateVisite
                            ];
                            $duplicatesRemoved++;
                        } else {
                            // L'équipement existant est plus récent ou égal, on garde l'ancien
                            $this->customLog("Conservation équipement {$numeroEquipement}: " . 
                                        $existingDate->format('Y-m-d H:i:s') . " >= " . $dateVisite->format('Y-m-d H:i:s'));
                            $duplicatesRemoved++;
                        }
                    }
                    
                } catch (\Exception $e) {
                    $this->customLog("Erreur lors de la déduplication pour {$numeroEquipement}: " . $e->getMessage());
                    
                    // En cas d'erreur, garder l'équipement s'il n'existe pas déjà
                    if (!isset($uniqueEquipments[$numeroEquipement])) {
                        $uniqueEquipments[$numeroEquipement] = [
                            'data' => $equipmentData,
                            'dateVisite' => new \DateTime('1970-01-01')
                        ];
                    }
                }
            }

            // Reconstruire le tableau final avec seulement les données d'équipement
            $equipmentsWithPictures = [];
            foreach ($uniqueEquipments as $uniqueEquipment) {
                $equipmentsWithPictures[] = $uniqueEquipment['data'];
            }

            $this->customLog("Nombre d'équipements après déduplication: " . count($equipmentsWithPictures));
            $this->customLog("Nombre de doublons supprimés: {$duplicatesRemoved}");
            $this->customLog("=== FIN DÉDUPLICATION ===");

            // Nettoyage mémoire après déduplication
            unset($uniqueEquipments);
            gc_collect_cycles();

            // LOG MÉMOIRE AVANT GÉNÉRATION PDF
            $beforePdfMemory = memory_get_usage(true);
            if ($beforePdfMemory > 0) {
                $this->customLog("Mémoire avant PDF: " . $this->formatBytes($beforePdfMemory));
            }

            // RÉSUMÉ PHOTOS
            $this->customLog("📊 RÉSUMÉ PHOTOS:");
            $this->customLog("- Photos scan dynamique: " . ($photoSourceStats['direct_scan'] ?? 0));
            $this->customLog("- Photos locales: " . ($photoSourceStats['local'] ?? 0)); 
            $this->customLog("- Photos API: " . ($photoSourceStats['api_fallback'] ?? 0));
            $this->customLog("- Aucune photo: " . ($photoSourceStats['none'] ?? 0));
            $this->customLog("- Erreurs: " . ($photoSourceStats['error'] ?? 0));
            
            $this->customLog("DEBUG - equipmentsWithPictures count: " . count($equipmentsWithPictures));
            
            // 7. SÉPARATION DES ÉQUIPEMENTS - VERSION SÉCURISÉE
            $equipementsSupplementaires = [];
            $equipementsNonPresents = [];
            
            foreach ($equipmentsWithPictures as $equipmentData) {
                try {
                    // Vérifier si la méthode isEnMaintenance existe avant de l'appeler
                    if (method_exists($equipmentData['equipment'], 'isEnMaintenance')) {
                        if ($equipmentData['equipment']->isEnMaintenance() === false) {
                            $equipementsSupplementaires[] = $equipmentData;
                        }
                    }
                    
                    // Équipements non présents
                    $etat = $equipmentData['equipment']->getEtat();
                    if ($etat === "Equipement non présent sur site" || $etat === "G") {
                        $equipementsNonPresents[] = $equipmentData;
                    }
                } catch (\Exception $e) {
                    $this->customLog("Erreur séparation équipement: " . $e->getMessage());
                }
            }
            
            $this->customLog("DEBUG - equipementsSupplementaires count: " . count($equipementsSupplementaires));
            
            // 8. CALCUL DES STATISTIQUES
            $statistiques = $this->calculateEquipmentStatisticsImproved($equipmentsFiltered);
            
            // 9. CALCUL DES STATISTIQUES SUPPLÉMENTAIRES
            $statistiquesSupplementaires = [];
            if (!empty($equipementsSupplementaires)) {
                $equipmentsSupplementairesOnly = array_map(function($item) {
                    return $item['equipment'];
                }, $equipementsSupplementaires);
                $statistiquesSupplementaires = $this->calculateEquipmentOffContractStatisticsImproved($equipmentsSupplementairesOnly);
            }
            
            // 10. GÉNÉRATION DU PDF AVEC MESSAGE D'AVERTISSEMENT
            $filename = "equipements_client_{$id}_{$agence}";
            if (!empty($clientAnneeFilter) || !empty($clientVisiteFilter)) {
                $filename .= '_filtered';
                if (!empty($clientAnneeFilter)) $filename .= "_{$clientAnneeFilter}";
                if (!empty($clientVisiteFilter)) $filename .= "_" . str_replace(' ', '_', $clientVisiteFilter);
            }
            $filename .= '.pdf';

            $nomClient = trim($clientSelectedInformations->getRaisonSociale());
            $adressep1 = trim($clientSelectedInformations->getAdressep1());
            $adressep2 = trim($clientSelectedInformations->getAdressep2());
            $cpostalp = trim($clientSelectedInformations->getCpostalp());
            $villep = trim($clientSelectedInformations->getVillep());
            $this->customLog("DEBUG - Client Address: {$nomClient}, {$adressep1} {$adressep2} {$cpostalp} {$villep}");

            // if ($statistiques['total'] && $statistiquesSupplementaires['total']) {
            //     $nombreEquipementsAuContrat = $statistiques['total'] - $statistiquesSupplementaires['total'];
            // }else {
            //     $nombreEquipementsAuContrat = $statistiques['total'] ?? 0;
            // }

            $templateVars = [
                'equipmentsWithPictures' => $this->convertStdClassToArray($equipmentsWithPictures),
                'equipementsSupplementaires' => $this->convertStdClassToArray($equipementsSupplementaires ?? []),
                'equipementsNonPresents' => $this->convertStdClassToArray($equipementsNonPresents ?? []),
                'withPhotos' => $withPhotos,
                'clientId' => $id,
                'agence' => $agence,
                'imageUrl' => $imageUrl,
                'clientAnneeFilter' => $clientAnneeFilter ?: '',
                'clientVisiteFilter' => $clientVisiteFilter ?: '',
                'statistiques' => $statistiques,
                'statistiquesSupplementaires' => $statistiquesSupplementaires,
                // 'nombreEquipementsAuContrat' => $nombreEquipementsAuContrat,
                'photoSourceStats' => $photoSourceStats,
                'isFiltered' => !empty($clientAnneeFilter) || !empty($clientVisiteFilter),
                'dateDeDerniererVisite' => $dateDeDerniererVisite,
                'derniereVisite' => $derniereVisite,
                'filtrage_success' => true,
                'total_equipements_bruts' => count($equipments),
                'total_equipements_filtres' => count($equipmentsFiltered),
                'nomClient' => $nomClient,
                'adressep1' => $adressep1,
                'adressep2' => $adressep2,
                'cpostalp' => $cpostalp,
                'villep' => $villep,
                // NOUVELLES VARIABLES POUR L'OPTIMISATION
                'isOptimizedMode' => count($equipmentsFiltered) > $maxEquipments,
                'maxEquipmentsProcessed' => min(count($equipmentsFiltered), $maxEquipments),
                'totalEquipmentsFound' => count($equipmentsFiltered),
                'optimizationMessage' => count($equipmentsFiltered) > $maxEquipments 
                    ? "Mode optimisé : Affichage des photos générales uniquement - " . count($equipmentsWithPictures) . " équipement(s) traité(s) sur " . count($equipmentsFiltered) . " total(aux)"
                    : null
            ];
            // Vérifier que imageUrl est bien définie
            if (empty($templateVars['imageUrl'])) {
                $templateVars['imageUrl'] = 'https://www.pdf.somafi-group.fr/background/group.jpg';
                $this->customLog("WARNING: imageUrl était vide, fallback utilisé");
            }
            
            $this->customLog("Génération du template avec " . count($equipmentsWithPictures) . " équipements");
            
            // Débugger ce qui est passé au template
            $this->customLog("=== TEMPLATE VARS DEBUG ===");
            foreach ($templateVars as $key => $value) {
                if (is_object($value)) {
                    $this->customLog("WARNING: $key est toujours un objet: " . get_class($value));
                } else {
                    $this->customLog("OK: $key est de type: " . gettype($value));
                }
            }

            $html = $this->renderView('pdf/equipements.html.twig', $templateVars);
            $pdfContent = $this->pdfGenerator->generatePdf($html, $filename);
            
            return new Response($pdfContent, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"$filename\"",
                'X-Equipment-Count' => count($equipmentsFiltered),
                'X-Equipment-Processed' => count($equipmentsWithPictures),
                'X-Filter-Applied' => $filtreApplique ? 'yes' : 'no',
                'X-Optimization-Applied' => count($equipmentsFiltered) > $maxEquipments ? 'yes' : 'no'
            ]);
            
        } catch (\Exception $e) {
            $this->customLog("ERREUR GÉNÉRATION PDF: " . $e->getMessage());
            return $this->generateLightErrorPdf($agence, $id, $e->getMessage(), $equipmentsFiltered);
        } finally {
            // Remettre les limites par défaut
            ini_restore('memory_limit');
            ini_restore('max_execution_time');
        }
    }

    private function getPhotosForEquipmentOptimized($equipment, string $agence): array // ← Ajoute le paramètre $agence
    {
        $numeroEquipement = $equipment->getNumeroEquipement();
        // ❌ NE PLUS UTILISER : $agence = $equipment->getCodeAgence() ?? 'S40';
        // ✅ Utiliser l'agence passée en paramètre
        
        $this->customLog("🏢 Agence utilisée: {$agence}");
        
        // Récupérer l'ID du contact (pas l'objet)
        $contactObject = $equipment->getIdContact();
        
        if (!$contactObject) {
            $this->customLog("❌ Pas d'objet contact pour l'équipement {$numeroEquipement}");
            return ['photos' => [], 'photos_indexed' => [], 'source' => 'no_id_contact', 'count' => 0];
        }
        
        // Si getIdContact() retourne un objet Contact, récupérer son ID
        if (is_object($contactObject)) {
            if (method_exists($contactObject, 'getIdContact')) {
                $idContact = $contactObject->getIdContact();
            } elseif (method_exists($contactObject, 'getId')) {
                $idContact = $contactObject->getId();
            } else {
                $this->customLog("❌ Impossible de récupérer l'ID depuis l'objet Contact");
                return ['photos' => [], 'photos_indexed' => [], 'source' => 'no_id_method', 'count' => 0];
            }
        } else {
            $idContact = $contactObject;
        }
        
        $this->customLog("📋 ID Contact récupéré: {$idContact}");
        
        // Déterminer si l'équipement est au contrat ou hors contrat
        $isEnMaintenance = method_exists($equipment, 'isEnMaintenance') ? $equipment->isEnMaintenance() : true;
        
        // ✅ Utiliser l'agence passée en paramètre
        $clientPath = $_SERVER['DOCUMENT_ROOT'] . "/public/img/{$agence}/{$idContact}/2025/CE1/";
        
        if (!is_dir($clientPath)) {
            $this->customLog("❌ Répertoire client n'existe pas: {$clientPath}");
            return ['photos' => [], 'photos_indexed' => [], 'source' => 'no_client_dir', 'count' => 0];
        }
        
        $this->customLog("🔍 Recherche photo pour équipement {$numeroEquipement} (id_contact: {$idContact}) dans {$clientPath}");
        
        $photoTypes = $isEnMaintenance 
            ? ['_generale.jpg', '_compte_rendu.jpg'] 
            : ['_compte_rendu.jpg', '_generale.jpg'];
        
        foreach ($photoTypes as $photoType) {
            $photoPath = $clientPath . $numeroEquipement . $photoType;
            
            if (file_exists($photoPath) && is_readable($photoPath)) {
                $this->customLog("✅ Photo trouvée: {$photoPath}");
                
                $photoContent = file_get_contents($photoPath);
                $photoEncoded = base64_encode($photoContent);
                
                $photoTypeName = str_replace(['_', '.jpg'], '', $photoType);
                
                return [
                    'photos' => [[
                        'picture' => $photoEncoded,
                        'update_time' => date('Y-m-d H:i:s', filemtime($photoPath)),
                        'photo_type' => $photoTypeName . '_locale'
                    ]],
                    'photos_indexed' => [$photoEncoded],
                    'source' => 'local_client_dir',
                    'count' => 1,
                    'client_folder_found' => $idContact,
                    'photo_type_found' => $photoTypeName
                ];
            } else {
                $this->customLog("⚠️ Photo non trouvée: {$photoPath}");
            }
        }
        
        $this->customLog("❌ Aucune photo trouvée pour {$numeroEquipement} dans le dossier client {$idContact}");
        return [
            'photos' => [], 
            'photos_indexed' => [], 
            'source' => 'not_found_in_client_dir', 
            'count' => 0, 
            'searched_path' => $clientPath
        ];
    }

    // 🔧 SOLUTION 1: Convertir tous les objets stdClass en tableaux
    private function convertStdClassToArray($data)
    {
        if (is_object($data) && get_class($data) === 'stdClass') {
            return (array) $data;
        } elseif (is_array($data)) {
            return array_map([$this, 'convertStdClassToArray'], $data);
        }
        return $data;
    }
    /**
     * Version allégée du PDF d'erreur
     */
    private function generateLightErrorPdf(string $agence, string $id, string $errorMessage, $equipmentsFiltered): Response
    {
        // ✅ SÉCURISER l'appel à memory_get_peak_usage
        // ✅ SÉCURISER l'appel à memory_get_peak_usage
        $peakMemory = memory_get_peak_usage(true);
        $memoryText = ($peakMemory > 0) ? $this->formatBytes($peakMemory) : 'N/A';
        
        // juste avant la génération du HTML/PDF

        // Activer le rapport détaillé des erreurs PHP
        set_error_handler(function($severity, $message, $file, $line) {
            $this->customLog("PHP Warning/Error: $message in $file at line $line");
            // Retourner false pour que PHP continue avec son gestionnaire normal
            return false;
        });

        // Vérifier toutes les variables numériques avant utilisation
        $this->customLog("=== VÉRIFICATION VARIABLES NUMÉRIQUES ===");
        $this->customLog("Memory usage: " . var_export(memory_get_usage(true), true));
        $this->customLog("Peak memory: " . var_export(memory_get_peak_usage(true), true));
        $this->customLog("Equipments count: " . var_export(count($equipmentsFiltered), true));

        // Vérifier les équipements pour des valeurs non-numériques
        foreach ($equipmentsFiltered as $index => $equipment) {
            if ($index < 3) { // Tester seulement les 3 premiers
                $this->customLog("Equipment $index methods check:");
                
                // Tester les getters qui pourraient retourner des valeurs numériques
                $numericMethods = ['getId', 'getNumeroEquipement'];
                foreach ($numericMethods as $method) {
                    if (method_exists($equipment, $method)) {
                        $value = $equipment->$method();
                        $this->customLog("  $method(): " . var_export($value, true) . " (type: " . gettype($value) . ")");
                    }
                }
            }
        }

        // Restaurer le gestionnaire d'erreurs par défaut après les tests
        restore_error_handler();

        $html = "
        <html><body style='font-family: Arial; padding: 20px;'>
            <h1>Erreur de génération PDF</h1>
            <p><strong>Client:</strong> {$id}</p>
            <p><strong>Agence:</strong> {$agence}</p>
            <p><strong>Erreur:</strong> {$errorMessage}</p>
            <p><strong>Mémoire pic:</strong> {$memoryText}</p>
            <p>Veuillez contacter le support technique.</p>
        </body></html>
        ";
        
        try {
            $pdfContent = $this->pdfGenerator->generatePdf($html, "erreur_{$agence}_{$id}.pdf");
            return new Response($pdfContent, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"erreur_{$agence}_{$id}.pdf\""
            ]);
        } catch (\Exception $e) {
            return new Response("Erreur critique de génération PDF", Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    
    /**
     * Formatage des tailles mémoire - VERSION CORRIGÉE
     */
    private function formatBytes(int $size, int $precision = 2): string
    {
        // ✅ PROTECTION contre les valeurs problématiques
        if ($size <= 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        // ✅ Utilisation de la méthode sécurisée sans log()
        $i = 0;
        while ($size > 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    /**
     * Logger personnalisé pour hébergement mutualisé
     */
    private function customLog(string $message): void
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/debug_photos.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        // Créer/écrire dans le fichier de log
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Afficher les logs via une route dédiée
     */
    #[Route('/debug/logs/photos', name: 'debug_photos_logs')]
    public function showPhotosLogs(): Response
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/debug_photos.log';
        
        if (!file_exists($logFile)) {
            return new Response("Aucun fichier de log trouvé", 404);
        }
        
        $logs = file_get_contents($logFile);
        
        // Récupérer seulement les 100 dernières lignes
        $lines = explode("\n", $logs);
        $lastLines = array_slice($lines, -100);
        
        $html = '<html><body>';
        $html .= '<h2>Debug Photos - Dernières 100 lignes</h2>';
        $html .= '<button onclick="location.reload()">Actualiser</button>';
        $html .= '<button onclick="clearLogs()">Vider les logs</button>';
        $html .= '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd;">';
        $html .= htmlspecialchars(implode("\n", $lastLines));
        $html .= '</pre>';
        $html .= '<script>
            function clearLogs() {
                if(confirm("Vider les logs ?")) {
                    fetch("/debug/clear-logs", {method: "POST"})
                        .then(() => location.reload());
                }
            }
        </script>';
        $html .= '</body></html>';
        
        return new Response($html);
    }

    #[Route('/debug/clear-logs', name: 'debug_clear_logs', methods: ['POST'])]
    public function clearLogs(): Response
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/debug_photos.log';
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }
        return new Response('OK');
    }

    /**
     * Diagnostic complet accessible via URL
     */
    #[Route('/debug/diagnostic/{agence}/{raisonSociale}', name: 'debug_diagnostic_complete')]
    public function diagnosticComplet(string $agence, string $raisonSociale, EntityManagerInterface $entityManager): Response
    {
        $results = [];
        
        try {
            $this->customLog("=== DÉBUT DIAGNOSTIC COMPLET ===");
            $this->customLog("Agence: {$agence}, Client: {$raisonSociale}");
            
            // 1. Test des répertoires
            $baseImagePath = $_SERVER['DOCUMENT_ROOT'] . '/public/img/' . $agence . '/';
            $this->customLog("Chemin base images: {$baseImagePath}");
            $this->customLog("Répertoire existe: " . (is_dir($baseImagePath) ? 'OUI' : 'NON'));
            
            if (is_dir($baseImagePath)) {
                $items = scandir($baseImagePath);
                $dirs = array_filter($items, function($item) use ($baseImagePath) {
                    return is_dir($baseImagePath . $item) && !in_array($item, ['.', '..']);
                });
                $this->customLog("Répertoires clients: " . implode(', ', $dirs));
                
                // Test spécifique du client
                $clientPath = $baseImagePath . $raisonSociale . '/';
                if (is_dir($clientPath)) {
                    $this->customLog("Répertoire client {$raisonSociale}: EXISTE");
                    
                    // Scanner les années
                    $years = array_filter(scandir($clientPath), function($item) use ($clientPath) {
                        return is_dir($clientPath . $item) && !in_array($item, ['.', '..']);
                    });
                    $this->customLog("Années disponibles: " . implode(', ', $years));
                    
                    // Test 2025
                    $year2025Path = $clientPath . '2025/';
                    if (is_dir($year2025Path)) {
                        $visits = array_filter(scandir($year2025Path), function($item) use ($year2025Path) {
                            return is_dir($year2025Path . $item) && !in_array($item, ['.', '..']);
                        });
                        $this->customLog("Types de visites 2025: " . implode(', ', $visits));
                        
                        // Test CEA et CE1
                        foreach (['CEA', 'CE1'] as $visitType) {
                            $visitPath = $year2025Path . $visitType . '/';
                            if (is_dir($visitPath)) {
                                $photos = array_filter(scandir($visitPath), function($item) {
                                    return pathinfo($item, PATHINFO_EXTENSION) === 'jpg';
                                });
                                $this->customLog("Photos dans {$visitType}: " . count($photos) . " fichiers");
                                if (count($photos) > 0) {
                                    $generales = array_filter($photos, function($photo) {
                                        return strpos($photo, 'generale') !== false;
                                    });
                                    $this->customLog("Photos générales dans {$visitType}: " . implode(', ', $generales));
                                }
                            }
                        }
                    }
                } else {
                    $this->customLog("Répertoire client {$raisonSociale}: N'EXISTE PAS");
                }
            }
            
            // 2. Test des équipements en base
            $repository = $this->getRepositoryForAgency($agence, $entityManager);
            $equipments = $repository->createQueryBuilder('e')
                ->where('e.raison_sociale LIKE :client')
                ->setParameter('client', "%{$raisonSociale}%")
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();
                
            $this->customLog("Équipements trouvés en base: " . count($equipments));
            
            foreach ($equipments as $equipment) {
                $this->customLog("--- Équipement: " . $equipment->getNumeroEquipement());
                $this->customLog("    Raison sociale: " . $equipment->getRaisonSociale());
                $this->customLog("    Visite: " . ($equipment->getVisite() ?? 'NULL'));
                
                // Test des 3 méthodes de récupération
                try {
                    $photos1 = $entityManager->getRepository(Form::class)
                        ->getGeneralPhotoFromLocalStorage($equipment, $entityManager);
                    $this->customLog("    Méthode 1 (local): " . (empty($photos1) ? "AUCUNE PHOTO" : count($photos1) . " photos"));
                } catch (\Exception $e) {
                    $this->customLog("    Méthode 1 (local): ERREUR - " . $e->getMessage());
                }
                
                try {
                    $photos2 = $entityManager->getRepository(Form::class)
                        ->findGeneralPhotoByScanning($equipment);
                    $this->customLog("    Méthode 2 (scan): " . (empty($photos2) ? "AUCUNE PHOTO" : count($photos2) . " photos"));
                } catch (\Exception $e) {
                    $this->customLog("    Méthode 2 (scan): ERREUR - " . $e->getMessage());
                }
                
                try {
                    $picturesArray = [
                        "numeroEquipement" => $equipment->getNumeroEquipement(),
                        "client" => explode("\\", $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale(),
                        "annee" => '2025',
                        "visite" => $equipment->getVisite() ?? 'CEA'
                    ];
                    
                    $photos3 = $entityManager->getRepository(Form::class)
                        ->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                    $this->customLog("    Méthode 3 (API): " . (empty($photos3) ? "AUCUNE PHOTO" : count($photos3) . " photos"));
                } catch (\Exception $e) {
                    $this->customLog("    Méthode 3 (API): ERREUR - " . $e->getMessage());
                }
            }
            
            // 3. Test données Form
            $formData = $entityManager->getRepository(Form::class)
                ->createQueryBuilder('f')
                ->where('f.raison_sociale_visite LIKE :client')
                ->setParameter('client', "%{$raisonSociale}\\CE1%")
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
                
            $this->customLog("Entrées Form trouvées: " . count($formData));
            foreach ($formData as $form) {
                $this->customLog("--- Form ID: " . $form->getId());
                $this->customLog("    Code équipement: " . $form->getCodeEquipement());
                $this->customLog("    Photo plaque: " . ($form->getPhotoPlaque() ? 'OUI' : 'NON'));
                $this->customLog("    Photo étiquette: " . ($form->getPhotoEtiquetteSomafi() ? 'OUI' : 'NON'));
            }
            
            $this->customLog("=== FIN DIAGNOSTIC COMPLET ===");
            
            $results['success'] = true;
            $results['message'] = 'Diagnostic terminé - consultez /debug/logs/photos pour voir les résultats';
            
        } catch (\Exception $e) {
            $this->customLog("ERREUR DIAGNOSTIC: " . $e->getMessage());
            $results['error'] = $e->getMessage();
        }
        
        return $this->json($results);
    }

    /**
     * Récupère le repository approprié selon le code agence
     */
    private function getRepositoryForAgency(string $agencyCode, EntityManagerInterface $entityManager)
    {
        $entityClass = match ($agencyCode) {
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
            default => throw new \InvalidArgumentException("Code agence non supporté : {$agencyCode}")
        };

        return $entityManager->getRepository($entityClass);
    }

    #[Route('/diagnostic/photos/{agence}/{clientId}', name: 'diagnostic_photos')]
    public function diagnosticPhotos(string $agence, string $clientId, EntityManagerInterface $entityManager): Response
    {
        $basePhotoPath = $_SERVER['DOCUMENT_ROOT'] . '/public/img/' . $agence . '/GEODIS_CORBAS/2025/CE1/';
        
        $results = [];
        
        // Vérifier si le répertoire existe
        if (is_dir($basePhotoPath)) {
            $files = scandir($basePhotoPath);
            $generalePhotos = array_filter($files, function($file) {
                return strpos($file, '_generale.jpg') !== false;
            });
            
            $results['directory_exists'] = true;
            $results['total_files'] = count($files) - 2; // Enlever . et ..
            $results['generale_photos'] = array_values($generalePhotos);
            $results['photo_count'] = count($generalePhotos);
        } else {
            $results['directory_exists'] = false;
            $results['expected_path'] = $basePhotoPath;
        }
        
        return $this->json($results);
    }

    /**
     * Génère un PDF d'erreur informatif
     */
    private function generateErrorPdf(string $agence, string $id, string $imageUrl, EntityManagerInterface $entityManager, string $errorMessage, array $debugInfo = []): Response
    {
        $this->customLog("Génération PDF d'erreur pour {$agence}/{$id}");
        
        $html = $this->renderView('pdf/equipements.html.twig', [
            'equipmentsWithPictures' => [],
            'equipementsSupplementaires' => [],
            'equipementsNonPresents' => [],
            'clientId' => $id,
            'agence' => $agence,
            'imageUrl' => $imageUrl,
            'clientAnneeFilter' => '',
            'clientVisiteFilter' => '',
            'error_mode' => true,
            'error_message' => $errorMessage,
            'debug_info' => $debugInfo,
            'isFiltered' => false,
            'dateDeDerniererVisite' => null
        ]);
        
        $filename = "equipements_client_{$id}_{$agence}_error.pdf";
        $pdfContent = $this->pdfGenerator->generatePdf($html, $filename);
        
        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"$filename\"",
            'X-Generation-Mode' => 'error'
        ]);
    }
    // ===== ROUTE DE DEBUG POUR ANALYSER LA STRUCTURE DES ÉQUIPEMENTS =====
    #[Route('/api/test-equipment/{agence}/{clientId}', name: 'api_test_equipment', methods: ['GET'])]
    public function testEquipmentStructure(
        string $agence,
        string $clientId,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $equipmentEntity = "App\\Entity\\Equipement{$agence}";
            
            if (!class_exists($equipmentEntity)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => "Classe {$equipmentEntity} n'existe pas"
                ]);
            }
            
            $repository = $entityManager->getRepository($equipmentEntity);
            $equipments = $repository->findBy(['id_contact' => $clientId], [], 3); // Prendre max 3 équipements
            
            if (empty($equipments)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => "Aucun équipement trouvé pour le client {$clientId}",
                    'total_in_table' => count($repository->findAll())
                ]);
            }
            
            // Analyser la structure du premier équipement
            $firstEquipment = $equipments[0];
            $methods = get_class_methods($firstEquipment);
            $getterMethods = array_filter($methods, function($method) {
                return strpos($method, 'get') === 0;
            });
            
            // Tester les valeurs
            $testValues = [];
            foreach ($getterMethods as $method) {
                try {
                    $value = $firstEquipment->$method();
                    if (is_scalar($value) && !empty($value)) {
                        $testValues[$method] = $value;
                    }
                } catch (\Exception $e) {
                    // Ignorer les méthodes qui requirent des paramètres
                }
            }
            
            return new JsonResponse([
                'success' => true,
                'entity' => $equipmentEntity,
                'equipment_count' => count($equipments),
                'available_getters' => $getterMethods,
                'sample_values' => $testValues,
                'potential_year_fields' => array_filter($getterMethods, function($method) {
                    return stripos($method, 'annee') !== false || 
                        stripos($method, 'year') !== false ||
                        stripos($method, 'date') !== false;
                }),
                'potential_visite_fields' => array_filter($getterMethods, function($method) {
                    return stripos($method, 'visite') !== false ||
                        stripos($method, 'maintenance') !== false ||
                        stripos($method, 'type') !== false;
                })
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Route pour télécharger un PDF stocké
     */
    #[Route('/pdf/download/{agence}/{clientId}/{annee}/{visite}', name: 'pdf_download')]
    public function downloadStoredPdf(
        string $agence,
        string $clientId,
        string $annee,
        string $visite
    ): Response {
        $pdfPath = $this->pdfStorageService->getPdfPath($agence, $clientId, $annee, $visite);
        
        if (!$pdfPath) {
            throw $this->createNotFoundException('PDF non trouvé');
        }
        
        $pdfContent = file_get_contents($pdfPath);
        $filename = "client_{$clientId}_{$annee}_{$visite}.pdf";
        
        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control' => 'private, max-age=3600',
            'Last-Modified' => date('D, d M Y H:i:s', filemtime($pdfPath)) . ' GMT'
        ]);
    }

    /**
     * Route SÉCURISÉE pour télécharger un PDF stocké
     * IMPORTANT: Cette route ne doit PAS être exposée directement au client
     */
    #[Route('/pdf/secure-download/{agence}/{clientId}/{annee}/{visite}', name: 'pdf_secure_download')]
    public function secureDownloadPdf(
        string $agence,
        string $clientId,
        string $annee,
        string $visite,
        Request $request
    ): Response {
        // SÉCURITÉ : Vérifier que la requête vient d'un lien court valide
        $referer = $request->headers->get('referer');
        $shortCode = $request->query->get('sc'); // Short code pour validation
        
        if (!$shortCode) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }
        
        // Valider le lien court
        $shortLink = $this->shortLinkService->getByShortCode($shortCode);
        if (!$shortLink || $shortLink->isExpired()) {
            throw $this->createNotFoundException('Lien expiré ou invalide');
        }
        
        // Vérifier que les paramètres correspondent au lien court
        if ($shortLink->getAgence() !== $agence || 
            $shortLink->getClientId() !== $clientId ||
            $shortLink->getAnnee() !== $annee ||
            $shortLink->getVisite() !== $visite) {
            throw $this->createAccessDeniedException('Paramètres invalides');
        }
        
        // Récupérer le PDF
        $pdfPath = $this->pdfStorageService->getPdfPath($agence, $clientId, $annee, $visite);
        
        if (!$pdfPath) {
            throw $this->createNotFoundException('PDF non trouvé');
        }
        
        // Enregistrer l'accès
        $this->shortLinkService->recordAccess($shortLink);
        
        $pdfContent = file_get_contents($pdfPath);
        $filename = "rapport_equipements_{$clientId}_{$annee}_{$visite}.pdf";
        
        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Last-Modified' => date('D, d M Y H:i:s', filemtime($pdfPath)) . ' GMT'
        ]);
    }

    /**
     * Route de redirection des liens courts SÉCURISÉE
     */
    #[Route('/s/{shortCode}', name: 'short_link_redirect')]
    public function redirectShortLink(string $shortCode): Response
    {
        $shortLink = $this->shortLinkService->getByShortCode($shortCode);
        
        if (!$shortLink) {
            // Afficher une page d'erreur personnalisée au lieu d'une 404 technique
            return $this->render('error/link_not_found.html.twig', [
                'message' => 'Ce lien n\'est plus valide ou a expiré.'
            ], new Response('', 410)); // 410 = Gone
        }
        
        // Enregistrer l'accès
        $this->shortLinkService->recordAccess($shortLink);
        
        // SÉCURITÉ : Construire l'URL sécurisée avec le code de validation
        $secureUrl = $this->generateUrl('pdf_secure_download', [
            'agence' => $shortLink->getAgence(),
            'clientId' => $shortLink->getClientId(),
            'annee' => $shortLink->getAnnee(),
            'visite' => $shortLink->getVisite()
        ]) . '?sc=' . $shortCode;
        
        return $this->redirect($secureUrl);
    }

    /**
     * NOUVELLE ROUTE pour envoyer un PDF existant par email - VERSION CORRIGÉE
     */
    #[Route('/client/equipements/send-email/{agence}/{id}', name: 'send_pdf_email', methods: ['POST'])]
    public function sendPdfByEmail(
        Request $request,
        string $agence,
        string $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $annee = $request->request->get('annee', date('Y'));
            $visite = $request->request->get('visite', 'CEA');
            $clientEmail = $request->request->get('client_email');
            
            if (!$clientEmail) {
                throw new \InvalidArgumentException('Email client requis');
            }
            
            // Validation de l'email
            if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Format d\'email invalide');
            }
            
            // Vérifier que le PDF existe ou le générer
            $pdfPath = $this->pdfStorageService->getPdfPath($agence, $id, $annee, $visite);
            if (!$pdfPath) {
                // Générer le PDF d'abord
                $subRequest = Request::create($this->generateUrl('client_equipements_pdf', [
                    'agence' => $agence,
                    'id' => $id
                ]) . "?clientAnneeFilter={$annee}&clientVisiteFilter={$visite}");
                
                $pdfResponse = $this->generateClientEquipementsPdf($subRequest, $agence, $id, $entityManager);
                
                // Vérifier si la génération a réussi
                if (!$pdfResponse instanceof Response || $pdfResponse->getStatusCode() !== 200) {
                    return new JsonResponse([
                        'success' => false,
                        'error' => 'Impossible de générer le PDF'
                    ], 500);
                }
                
                // Réessayer de récupérer le chemin du PDF après génération
                $pdfPath = $this->pdfStorageService->getPdfPath($agence, $id, $annee, $visite);
                if (!$pdfPath) {
                    return new JsonResponse([
                        'success' => false,
                        'error' => 'PDF généré mais non stocké'
                    ], 500);
                }
            }
            
            // Créer ou récupérer le lien court
            $originalUrl = $this->generateUrl('pdf_secure_download', [
                'agence' => $agence,
                'clientId' => $id,
                'annee' => $annee,
                'visite' => $visite
            ], true);
            
            $expiresAt = (new \DateTime())->modify('+30 days');
            
            $shortLink = $this->shortLinkService->createShortLink(
                $originalUrl,
                $agence,
                $id,
                $annee,
                $visite,
                $expiresAt
            );
            
            $shortUrl = $this->shortLinkService->getShortUrl($shortLink->getShortCode());
            
            // Récupérer les infos client (nom seulement, email vient de la requête)
            $clientInfo = $this->getClientInfo($agence, $id, $entityManager);
            $clientName = $clientInfo['nom'] ?? "Client $id";
            
            // Générer le trigramme utilisateur
            $userTrigramme = $this->generateUserTrigramme();
            
            // ✅ CORRECTION : Utiliser $clientEmail au lieu de $clientInfo['email']
            $emailSent = $this->emailService->sendPdfLinkToClient(
                $agence,
                $clientEmail,  // ← Email de la requête, pas de la BDD
                $clientName,
                $shortUrl,
                $annee,
                $visite,
                $userTrigramme
            );
            
            // Enregistrer l'envoi avec les bonnes informations
            $this->recordEmailSent($agence, $id, $clientEmail, $shortUrl, $emailSent, $userTrigramme, $entityManager);
            
            return new JsonResponse([
                'success' => $emailSent,
                'message' => $emailSent ? 'Email envoyé avec succès' : 'Erreur lors de l\'envoi',
                'short_url' => $shortUrl,
                'short_code' => $shortLink->getShortCode(),
                'client_email' => $clientEmail,
                'client_name' => $clientName
            ]);
            
        } catch (\Exception $e) {
            $this->customLog("Erreur sendPdfByEmail: " . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Méthode corrigée pour enregistrer l'envoi d'email avec toutes les infos nécessaires
     */
    private function recordEmailSent(
        string $agence, 
        string $clientId, 
        string $clientEmail, 
        string $shortUrl, 
        bool $success, 
        string $userTrigramme,
        EntityManagerInterface $entityManager
    ): void {
        try {
            $mailEntity = "App\\Entity\\Mail{$agence}";
            $contactEntity = "App\\Entity\\Contact{$agence}";
            
            if (!class_exists($mailEntity)) {
                $this->customLog("Classe Mail{$agence} n'existe pas");
                return;
            }
            
            $contact = $entityManager->getRepository($contactEntity)->findOneBy(['id_contact' => $clientId]);
            
            if (!$contact) {
                $this->customLog("Contact {$clientId} non trouvé pour enregistrement email");
                return;
            }
            
            $mail = new $mailEntity();
            $mail->setIdContact($contact);
            $mail->setPdfUrl($shortUrl);
            $mail->setIsPdfSent($success);
            $mail->setSentAt(new \DateTimeImmutable());
            $mail->setSender($userTrigramme);
            $mail->setPdfFilename("client_{$clientId}.pdf");
            
            // Ajouter l'email de destination si la propriété existe
            if (method_exists($mail, 'setRecipientEmail')) {
                $mail->setRecipientEmail($clientEmail);
            }
            
            $entityManager->persist($mail);
            $entityManager->flush();
            
            $this->customLog("Email enregistré avec succès pour client {$clientId}");
            
        } catch (\Exception $e) {
            $this->customLog("Erreur enregistrement email: " . $e->getMessage());
        }
    }

    /**
     * Génère le trigramme à partir des informations de l'utilisateur connecté - VERSION CORRIGÉE
     */
    private function generateUserTrigramme(): string
    {
        $user = $this->getUser();
        
        if (!$user) {
            return 'SYS'; // Fallback si pas d'utilisateur connecté
        }
        
        try {
            // Essayer différentes méthodes selon votre entité User
            $firstName = '';
            $lastName = '';
            
            // Tester les getters possibles pour le prénom
            if (method_exists($user, 'getPrenom')) {
                $firstName = $user->getPrenom();
            }

            // Tester les getters possibles pour le nom
            if (method_exists($user, 'getNom')) {
                $lastName = $user->getNom();
            }
            
            // Nettoyer et normaliser les chaînes
            $firstName = strtoupper(trim($firstName));
            $lastName = strtoupper(trim($lastName));
            
            // Construire le trigramme
            $trigramme = '';
            
            // 1ère lettre du prénom
            if (!empty($firstName)) {
                $trigramme .= substr($firstName, 0, 1);
            } else {
                $trigramme .= 'U'; // U pour User
            }
            
            // 2 premières lettres du nom
            if (!empty($lastName)) {
                if (strlen($lastName) >= 2) {
                    $trigramme .= substr($lastName, 0, 2);
                } else {
                    $trigramme .= $lastName . 'X';
                }
            } else {
                $trigramme .= 'SR'; // SR pour SeR (utilisateur)
            }
            
            $this->customLog("Trigramme généré: {$trigramme} (Prénom: {$firstName}, Nom: {$lastName})");
            
            return $trigramme;
            
        } catch (\Exception $e) {
            $this->customLog("Erreur génération trigramme: " . $e->getMessage());
            return 'USR'; // Fallback générique
        }
    }
    private function getClientInfo(string $agence, string $id, EntityManagerInterface $entityManager): array
    {
        try {
            $contactEntity = "App\\Entity\\Contact{$agence}";
            
            if (class_exists($contactEntity)) {
                $contact = $entityManager->getRepository($contactEntity)->findOneBy(['id_contact' => $id]);
                
                if ($contact) {
                    $nom = '';
                    $email = '';
                    
                    // Pour ContactS50, tester les méthodes disponibles
                    // Récupération du nom/raison sociale
                    if (method_exists($contact, 'getRaisonSociale')) {
                        $nom = $contact->getRaisonSociale();
                    } elseif (method_exists($contact, 'getNom')) {
                        $nom = $contact->getNom();
                    } elseif (method_exists($contact, 'getLibelle')) {
                        $nom = $contact->getLibelle();
                    } elseif (method_exists($contact, 'getNomContact')) {
                        $nom = $contact->getNomContact();
                    }
                    
                    // Récupération de l'email - tester plusieurs possibilités
                    if (method_exists($contact, 'getEmail')) {
                        $email = $contact->getEmail();
                    } elseif (method_exists($contact, 'getEmailContact')) {
                        $email = $contact->getEmailContact();
                    } elseif (method_exists($contact, 'getMail')) {
                        $email = $contact->getMail();
                    } elseif (method_exists($contact, 'getEmailClient')) {
                        $email = $contact->getEmailClient();
                    }
                    
                    // Debug pour voir les méthodes disponibles sur ContactS50
                    $this->customLog("DEBUG ContactS50 - Méthodes disponibles: " . implode(', ', get_class_methods($contact)));
                    $this->customLog("DEBUG ContactS50 - Nom trouvé: " . ($nom ?: 'VIDE'));
                    $this->customLog("DEBUG ContactS50 - Email trouvé: " . ($email ?: 'VIDE'));
                    
                    return [
                        'nom' => $nom ?: "Client {$id}",
                        'email' => $email ?: '',
                        'id_contact' => $id,
                        'agence' => $agence
                    ];
                } else {
                    $this->customLog("DEBUG: Contact non trouvé pour ID {$id} dans {$contactEntity}");
                }
            } else {
                $this->customLog("DEBUG: Classe {$contactEntity} n'existe pas");
            }
        } catch (\Exception $e) {
            $this->customLog("Erreur récupération client info: " . $e->getMessage());
        }
        
        return [
            'nom' => "Client {$id}",
            'email' => '',
            'id_contact' => $id,
            'agence' => $agence
        ];
    }

    // ===== 2. AJOUT DE LA ROUTE API POUR RÉCUPÉRER LES INFOS CLIENT =====
    #[Route('/api/client-info/{agence}/{id}', name: 'api_client_info', methods: ['GET'])]
    public function getClientInfoApi(
        string $agence,
        string $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $clientInfo = $this->getClientInfo($agence, $id, $entityManager);
            
            return new JsonResponse([
                'success' => true,
                'client' => $clientInfo,
                'debug' => [
                    'agence' => $agence,
                    'id' => $id,
                    'entity_class' => "App\\Entity\\Contact{$agence}"
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'client' => [
                    'nom' => "Client {$id}",
                    'email' => '',
                    'id_contact' => $id,
                    'agence' => $agence
                ]
            ], 500);
        }
    }

    // ===== 4. MÉTHODE POUR TESTER LA CONNEXION À LA BASE DE DONNÉES ContactS50 =====
    #[Route('/api/test-contact/{agence}/{id}', name: 'api_test_contact', methods: ['GET'])]
    public function testContactConnection(
        string $agence,
        string $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $contactEntity = "App\\Entity\\Contact{$agence}";
            
            if (!class_exists($contactEntity)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => "Classe {$contactEntity} n'existe pas"
                ]);
            }
            
            // Récupérer le contact
            $contact = $entityManager->getRepository($contactEntity)->findOneBy(['id_contact' => $id]);
            
            if (!$contact) {
                // Essayer de lister quelques contacts pour debug
                $allContacts = $entityManager->getRepository($contactEntity)->findBy([], [], 5);
                
                return new JsonResponse([
                    'success' => false,
                    'error' => "Contact {$id} non trouvé",
                    'debug' => [
                        'entity' => $contactEntity,
                        'total_contacts_sample' => count($allContacts),
                        'sample_ids' => array_map(function($c) {
                            return method_exists($c, 'getIdContact') ? $c->getIdContact() : 'N/A';
                        }, $allContacts)
                    ]
                ]);
            }
            
            // Analyser les méthodes disponibles
            $methods = get_class_methods($contact);
            $getterMethods = array_filter($methods, function($method) {
                return strpos($method, 'get') === 0;
            });
            
            // Tester les getters pour nom et email
            $testResults = [];
            foreach ($getterMethods as $method) {
                try {
                    $value = $contact->$method();
                    if (is_string($value) && !empty($value)) {
                        $testResults[$method] = substr($value, 0, 50); // Limiter pour l'affichage
                    }
                } catch (\Exception $e) {
                    // Ignorer les méthodes qui requirent des paramètres
                }
            }
            
            return new JsonResponse([
                'success' => true,
                'contact_found' => true,
                'entity' => $contactEntity,
                'available_getters' => $getterMethods,
                'test_values' => $testResults,
                'potential_email_methods' => array_filter($getterMethods, function($method) {
                    return stripos($method, 'email') !== false || stripos($method, 'mail') !== false;
                }),
                'potential_name_methods' => array_filter($getterMethods, function($method) {
                    return stripos($method, 'nom') !== false || 
                        stripos($method, 'raison') !== false || 
                        stripos($method, 'libelle') !== false ||
                        stripos($method, 'name') !== false;
                })
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * ✅ NOUVELLE MÉTHODE : Convertit les codes d'état en libellés lisibles
     */
    private function convertEtatCodeToLibelle(string $etatCode): string
    {
        switch ($etatCode) {
            case 'A':
                return 'Bon état';
            case 'B':
                return 'Travaux à prévoir';
            case 'C':
                return 'Travaux curatifs urgents';
            case 'D':
                return 'Equipement inaccessible';
            case 'E':
            case 'F':
                return 'Equipement à l\'arrêt';
            case 'G':
                return 'Equipement non présent sur site';
            default:
                // Si ce n'est pas un code, retourner tel quel (déjà un libellé)
                return $etatCode;
        }
    }
    
    private function getEquipmentByAgence(string $agence, string $id, EntityManagerInterface $entityManager)
    {
        switch ($agence) {
            case 'S10':
                return $entityManager->getRepository(EquipementS10::class)->findOneBy(['id' => $id]);
            case 'S40':
                return $entityManager->getRepository(EquipementS40::class)->findOneBy(['id' => $id]);
            case 'S50':
                return $entityManager->getRepository(EquipementS50::class)->findOneBy(['id' => $id]);
            case 'S60':
                return $entityManager->getRepository(EquipementS60::class)->findOneBy(['id' => $id]);
            case 'S70':
                return $entityManager->getRepository(EquipementS70::class)->findOneBy(['id' => $id]);
            case 'S80':
                return $entityManager->getRepository(EquipementS80::class)->findOneBy(['id' => $id]);
            case 'S100':
                return $entityManager->getRepository(EquipementS100::class)->findOneBy(['id' => $id]);
            case 'S120':
                return $entityManager->getRepository(EquipementS120::class)->findOneBy(['id' => $id]);
            case 'S130':
                return $entityManager->getRepository(EquipementS130::class)->findOneBy(['id' => $id]);
            case 'S140':
                return $entityManager->getRepository(EquipementS140::class)->findOneBy(['id' => $id]);
            case 'S150':
                return $entityManager->getRepository(EquipementS150::class)->findOneBy(['id' => $id]);
            case 'S160':
                return $entityManager->getRepository(EquipementS160::class)->findOneBy(['id' => $id]);
            case 'S170':
                return $entityManager->getRepository(EquipementS170::class)->findOneBy(['id' => $id]);
            default:
                return null;
        }
    }

    /**
     * Méthode simplifiée pour récupérer les équipements EN BDD sans filtrage
     * CORRECTION: Ne plus appeler getEquipmentsByAgencyFixed avec des filtres
     */
    private function getEquipmentsByClientAndAgence(string $agence, string $clientId, EntityManagerInterface $entityManager): array
    {
        try {
            $this->customLog("Récupération équipements pour agence: {$agence}, client: {$clientId}");
            
            // Utiliser la méthode appropriée selon l'agence
            switch ($agence) {
                case 'S10':
                    return $entityManager->getRepository(EquipementS10::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S40':
                    return $entityManager->getRepository(EquipementS40::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S50':
                    return $entityManager->getRepository(EquipementS50::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S60':
                    return $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S70':
                    return $entityManager->getRepository(EquipementS70::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S80':
                    return $entityManager->getRepository(EquipementS80::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S100':
                    return $entityManager->getRepository(EquipementS100::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S120':
                    return $entityManager->getRepository(EquipementS120::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S130':
                    return $entityManager->getRepository(EquipementS130::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S140':
                    return $entityManager->getRepository(EquipementS140::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S150':
                    return $entityManager->getRepository(EquipementS150::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S160':
                    return $entityManager->getRepository(EquipementS160::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S170':
                    return $entityManager->getRepository(EquipementS170::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                default:
                    return [];
            }
            
            $this->customLog("Trouvé " . count($equipments) . " équipements pour {$agence}/{$clientId}");
            return $equipments;
            
        } catch (\Exception $e) {
            $this->customLog("Erreur récupération équipements {$agence}/{$clientId}: " . $e->getMessage());
            return [];
        }
    }

    private function getImageUrlForAgency(string $agencyName): string
    {
        // Assurer que cela renvoie un chemin absolu
        $basePath = 'https://www.pdf.somafi-group.fr/background/';

        // Assurez-vous d'ajouter vos conditions pour les URL spécifiques
        switch ($agencyName) {
            case 'S10':
                return 'https://www.pdf.somafi-group.fr/background/group.jpg';
            case 'S40':
                return 'https://www.pdf.somafi-group.fr/background/st-etienne.jpg';
            case 'S50':
                return 'https://www.pdf.somafi-group.fr/background/grenoble.jpg';
            case 'S60':
                return 'https://www.pdf.somafi-group.fr/background/lyon.jpg';
            case 'S70':
                return 'https://www.pdf.somafi-group.fr/background/bordeaux.jpg';
            case 'S80':
                return 'https://www.pdf.somafi-group.fr/background/paris.jpg';
            case 'S100':
                return 'https://www.pdf.somafi-group.fr/background/montpellier.jpg';
            case 'S120':
                return 'https://www.pdf.somafi-group.fr/background/portland.jpg';
            case 'S130':
                return 'https://www.pdf.somafi-group.fr/background/toulouse.jpg';
            case 'S140':
                return 'https://www.pdf.somafi-group.fr/background/grand-est.jpg';
            case 'S150':
                return 'https://www.pdf.somafi-group.fr/background/paca.jpg';
            case 'S160':
                return 'https://www.pdf.somafi-group.fr/background/rouen.jpg';
            case 'S170':
                return 'https://www.pdf.somafi-group.fr/background/rennes.jpg';
            default:
                return 'https://www.pdf.somafi-group.fr/background/group.jpg'; // Image par défaut
        }
    }

    /**
     * Méthode améliorée pour calculer les statistiques avec gestion d'erreurs
     */
    private function calculateEquipmentStatisticsImproved(array $equipments): array
    {
        $total = count($equipments);
        $statusCounts = [
            'green' => 0,
            'orange' => 0, 
            'red' => 0,
            'black' => 0,
            'unknown' => 0
        ];
        
        $visitedCount = 0;
        
        foreach ($equipments as $equipment) {
            if ($equipment->isEnMaintenance() == true) {
                // Compter les équipements visités (avec photos ou état)
                if ($equipment->getEtat() || $equipment->getDerniereVisite()) {
                    $visitedCount++;
                }
                // Compter par état
                $etat = $equipment->getEtat();
                switch ($etat) {
                    case 'Bon état':
                    case 'A':
                        $statusCounts['green']++;
                        break;
                    case 'Travaux à prévoir':
                    case 'B':
                        $statusCounts['orange']++;
                        break;
                    case 'Travaux curatifs urgents':
                    case 'Travaux urgent ou à l\'arrêt':
                    case 'C':
                    case 'E':
                    case 'F':
                        $statusCounts['red']++;
                        break;
                    case 'Equipement à l\'arrêt':
                    case 'Equipement à l\'arrêt le jour de la visite':
                    case 'Equipement non présent sur site':
                    case 'G':
                    case 'D':
                    case 'Equipement inaccessible':
                        $statusCounts['black']++;
                        break;
                    default:
                        $statusCounts['unknown']++;
                        break;
                }
            }    
        }
        
        return [
            'total' => $total,
            'visitedCount' => $visitedCount,
            'status_counts' => $statusCounts
        ];
    }
    /**
     * Méthode améliorée pour calculer les statistiques avec gestion d'erreurs
     */
    private function calculateEquipmentOffContractStatisticsImproved(array $equipments): array
    {
        $total = count($equipments);
        $statusCounts = [
            'green' => 0,
            'orange' => 0, 
            'red' => 0,
            'black' => 0,
            'unknown' => 0
        ];
        
        $visitedCount = 0;
        
        foreach ($equipments as $equipment) {
            if ($equipment->isEnMaintenance() == false) {
                // Compter les équipements visités (avec photos ou état)
                if ($equipment->getEtat() || $equipment->getDerniereVisite()) {
                    $visitedCount++;
                }
                // Compter par état
                $etat = $equipment->getEtat();
                switch ($etat) {
                    case 'Bon état':
                    case 'A':
                        $statusCounts['green']++;
                        break;
                    case 'Travaux à prévoir':
                    case 'B':
                        $statusCounts['orange']++;
                        break;
                    case 'Travaux curatifs urgents':
                    case 'Travaux urgent ou à l\'arrêt':
                    case 'C':
                    case 'D':
                    case 'E':
                        $statusCounts['red']++;
                        break;
                    case 'Equipement inaccessible':
                        $statusCounts['black']++;
                        break;
                    default:
                        $statusCounts['unknown']++;
                        break;
                }
            }    
        }
        
        return [
            'total' => $total,
            'visitedCount' => $visitedCount,
            'status_counts' => $statusCounts
        ];
    }
}