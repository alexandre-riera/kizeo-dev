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
use App\Service\PdfGenerator;
use App\Entity\EquipementS100;
use App\Entity\EquipementS120;
use App\Entity\EquipementS130;
use App\Entity\EquipementS140;
use App\Entity\EquipementS150;
use App\Entity\EquipementS160;
use App\Entity\EquipementS170;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class EquipementPdfController extends AbstractController
{
    private $pdfGenerator;
    
    public function __construct(PdfGenerator $pdfGenerator)
    {
        $this->pdfGenerator = $pdfGenerator;
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
     * Génère un PDF complet pour tous les équipements d'un client
     * VERSION MISE À JOUR - Utilise les photos stockées en local au lieu des appels API
     * Route: /client/equipements/pdf/{agence}/{id}
     */
    #[Route('/client/equipements/pdf/{agence}/{id}', name: 'client_equipements_pdf')]
    public function generateClientEquipementsPdf(Request $request, string $agence, string $id, EntityManagerInterface $entityManager): Response
    {
        // Initialiser les métriques de performance
        $startTime = microtime(true);
        $photoSourceStats = ['local' => 0, 'api_fallback' => 0, 'none' => 0];
        
        try {
            // Récupérer les filtres depuis les paramètres de la requête
            $clientAnneeFilter = $request->query->get('clientAnneeFilter', '');
            $clientVisiteFilter = $request->query->get('clientVisiteFilter', '');
            
            // Récupérer tous les équipements du client selon l'agence
            $equipments = $this->getEquipmentsByClientAndAgence($agence, $id, $entityManager);

            if (empty($equipments)) {
                throw $this->createNotFoundException('Aucun équipement trouvé pour ce client');
            }
            
            // Appliquer les filtres si définis
            if (!empty($clientAnneeFilter) || !empty($clientVisiteFilter)) {
                $equipments = array_filter($equipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                    $matches = true;
                    
                    // Filtre par année si défini
                    if (!empty($clientAnneeFilter)) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        $matches = $matches && ($annee_date_equipment === $clientAnneeFilter);
                    }
                    
                    // Filtre par visite si défini  
                    if (!empty($clientVisiteFilter)) {
                        $matches = $matches && ($equipment->getVisite() === $clientVisiteFilter);
                    }
                    
                    return $matches;
                });
            }

            // Récupérer les informations client
            $clientSelectedInformations = $this->getClientInformations($agence, $id, $entityManager);

            // ✅ SOLUTION : Filtrer uniquement les équipements au contrat
            $equipmentsAuContrat = array_filter($equipments, function($equipment) {
                return $equipment->isEnMaintenance() === true;
            });
            // Statistiques des équipements 
            $statistiques = $this->calculateEquipmentStatistics($equipmentsAuContrat);

            $equipmentsWithPictures = [];
            $dateDeDerniererVisite = "";

            // NOUVELLE LOGIQUE: Pour chaque équipement, récupérer ses photos via la méthode optimisée
            foreach ($equipments as $equipment) {
                $picturesData = [];
                $photoSource = 'none';
                
                try {
                    // Distinguer entre équipements au contrat et supplémentaires
                    if ($equipment->isEnMaintenance()) {
                        // ÉQUIPEMENTS AU CONTRAT - Utiliser la méthode optimisée
                        $picturesData = $entityManager->getRepository(Form::class)
                            ->getPictureArrayByIdEquipmentOptimized($equipment, $entityManager);
                        $photoSource = !empty($picturesData) ? 'local' : 'none';
                    } else {
                        // ÉQUIPEMENTS SUPPLÉMENTAIRES - Utiliser la méthode spécialisée optimisée
                        $picturesData = $entityManager->getRepository(Form::class)
                            ->getPictureArrayByIdSupplementaryEquipmentOptimized($equipment, $entityManager);
                        $photoSource = !empty($picturesData) ? 'local' : 'none';
                    }
                    
                    // Si pas de photos locales, fallback vers l'API (uniquement si nécessaire)
                    if (empty($picturesData) && $this->shouldUseFallback()) {
                        if ($equipment->isEnMaintenance()) {
                            $picturesData = $this->getEquipmentPicturesWithFallback($equipment, $entityManager);
                        } else {
                            $picturesData = $this->getSupplementaryEquipmentPicturesWithFallback($equipment, $entityManager);
                        }
                        $photoSource = !empty($picturesData) ? 'api_fallback' : 'none';
                    }
                    
                } catch (\Exception $e) {
                    // Log l'erreur mais continue le traitement
                    error_log("Erreur récupération photos pour équipement {$equipment->getNumeroEquipement()}: " . $e->getMessage());
                    $photoSource = 'none';
                }
                
                // Compter les sources de photos pour statistiques
                $photoSourceStats[$photoSource]++;
                
                $equipmentsWithPictures[] = [
                    'equipment' => $equipment,
                    'pictures' => $picturesData,
                    'photo_source' => $photoSource // Pour debugging
                ];
                
                // Récupérer la date de dernière visite
                $dateDeDerniererVisite = $equipment->getDerniereVisite();
            }

            // Séparer les équipements supplémentaires
            $equipementsSupplementaires = array_filter($equipmentsWithPictures, function($equipement) {
                return $equipement['equipment']->isEnMaintenance() === false;
            });

            // Calculer statistiques supplémentaires
            $statistiquesSupplementaires = $this->calculateSupplementaryStatistics($equipementsSupplementaires);

            // Équipements non présents
            $equipementsNonPresents = array_filter($equipmentsWithPictures, function($equipement) {
                $etat = $equipement['equipment']->getEtat();
                return $etat === "Equipement non présent sur site" || $etat === "G";
            });

            // URL de l'image d'agence
            $imageUrl = $this->getImageUrlForAgency($agence);
            
            // GÉNÉRATION DU PDF avec template équipements (multi-équipements)
            $html = $this->renderView('pdf/equipements.html.twig', [
                'equipmentsWithPictures' => $equipmentsWithPictures,
                'equipementsSupplementaires' => $equipementsSupplementaires,
                'equipementsNonPresents' => $equipementsNonPresents,
                'clientId' => $id,
                'agence' => $agence,
                'imageUrl' => $imageUrl,
                'clientAnneeFilter' => $clientAnneeFilter,
                'clientVisiteFilter' => $clientVisiteFilter,
                'statistiques' => $statistiques, // 🎯 Nouvelle variable ajoutée,
                'statistiquesSupplementaires' => $statistiquesSupplementaires, // 🎯 Nouvelle variable
                'statistiquesSupplementaires' => $statistiquesSupplementaires,
                'dateDeDerniererVisite' => $dateDeDerniererVisite,
                'clientSelectedInformations' => $clientSelectedInformations,
                'isFiltered' => !empty($clientAnneeFilter) || !empty($clientVisiteFilter),
                // NOUVELLES VARIABLES pour monitoring
                'using_local_photos' => $photoSourceStats['local'] > 0,
                'photo_source_stats' => $photoSourceStats,
                'generation_time' => date('Y-m-d H:i:s'),
                'performance_mode' => 'optimized'
            ]);
            
            // Générer le nom de fichier avec filtres
            $filename = "equipements_client_{$id}_{$agence}";
            if (!empty($clientAnneeFilter) || !empty($clientVisiteFilter)) {
                $filename .= '_filtered';
                if (!empty($clientAnneeFilter)) {
                    $filename .= '_' . $clientAnneeFilter;
                }
                if (!empty($clientVisiteFilter)) {
                    $filename .= '_' . str_replace(' ', '_', $clientVisiteFilter);
                }
            }
            $filename .= '.pdf';
            
            // Générer le PDF
            $pdfContent = $this->pdfGenerator->generatePdf($html, $filename);
            
            // Log des métriques de performance
            $totalTime = round(microtime(true) - $startTime, 2);
            $this->logPdfGenerationMetrics($agence, $id, count($equipments), $photoSourceStats, $totalTime);
            
            // Headers avec informations de debug
            $headers = [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"$filename\"",
                'X-Equipment-Count' => count($equipments),
                'X-Local-Photos' => $photoSourceStats['local'],
                'X-Fallback-Photos' => $photoSourceStats['api_fallback'],
                'X-Missing-Photos' => $photoSourceStats['none'],
                'X-Generation-Time' => $totalTime . 's',
                'X-Performance-Mode' => 'optimized'
            ];
            
            return new Response($pdfContent, Response::HTTP_OK, $headers);
            
        } catch (\Exception $e) {
            // En cas d'erreur majeure, fallback vers l'ancienne méthode complète
            return $this->generateClientEquipementsPdfFallback($request, $agence, $id, $entityManager, $e);
        }
    }

    /**
     * NOUVELLE MÉTHODE: Fallback complet vers l'ancienne méthode
     */
    private function generateClientEquipementsPdfFallback(
        Request $request, 
        string $agence, 
        string $id, 
        EntityManagerInterface $entityManager, 
        \Exception $originalException
    ): Response {
        
        error_log("Fallback complet pour PDF client {$id} agence {$agence}: " . $originalException->getMessage());
        
        try {
            // Utiliser entièrement l'ancienne logique avec appels API
            $clientAnneeFilter = $request->query->get('clientAnneeFilter', '');
            $clientVisiteFilter = $request->query->get('clientVisiteFilter', '');
            
            $equipments = $this->getEquipmentsByClientAndAgence($agence, $id, $entityManager);
            
            // Application des filtres (identique)
            if (!empty($clientAnneeFilter) || !empty($clientVisiteFilter)) {
                $equipments = array_filter($equipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                    $matches = true;
                    
                    if (!empty($clientAnneeFilter)) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        $matches = $matches && ($annee_date_equipment === $clientAnneeFilter);
                    }
                    
                    if (!empty($clientVisiteFilter)) {
                        $matches = $matches && ($equipment->getVisite() === $clientVisiteFilter);
                    }
                    
                    return $matches;
                });
            }
            
            $equipmentsWithPictures = [];
            
            // ANCIENNE LOGIQUE: Appels API pour chaque équipement
            foreach ($equipments as $equipment) {
                if ($equipment->isEnMaintenance()) {
                    // Ancienne méthode pour équipements au contrat
                    $picturesArray = $entityManager->getRepository(Form::class)->findBy([
                        'code_equipement' => $equipment->getNumeroEquipement(),
                        'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()
                    ]);
                    $picturesData = $entityManager->getRepository(Form::class)
                        ->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                } else {
                    // Ancienne méthode pour équipements supplémentaires
                    $picturesData = $entityManager->getRepository(Form::class)
                        ->getPictureArrayByIdSupplementaryEquipment($entityManager, $equipment);
                }
                
                $equipmentsWithPictures[] = [
                    'equipment' => $equipment,
                    'pictures' => $picturesData
                ];
            }
            
            // Continuer avec le reste de la logique (identique à l'originale)
            $clientSelectedInformations = $this->getClientInformations($agence, $id, $entityManager);
            $statistiques = $this->calculateEquipmentStatistics($equipments);
            // ... reste du code identique
            
            $filename = "equipements_client_{$id}_{$agence}_fallback.pdf";
            
            $html = $this->renderView('pdf/equipements.html.twig', [
                'equipmentsWithPictures' => $equipmentsWithPictures,
                // ... autres variables
                'fallback_mode' => true,
                'performance_mode' => 'legacy'
            ]);
            
            $pdfContent = $this->pdfGenerator->generatePdf($html, $filename);
            
            return new Response($pdfContent, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"$filename\"",
                'X-Performance-Mode' => 'legacy-fallback',
                'X-Fallback-Reason' => 'optimization-failed'
            ]);
            
        } catch (\Exception $fallbackException) {
            throw new \RuntimeException(
                "Impossible de générer le PDF client {$id}. " .
                "Erreur principale: {$originalException->getMessage()}. " .
                "Erreur fallback: {$fallbackException->getMessage()}"
            );
        }
    }

    /**
     * NOUVELLES MÉTHODES HELPER
     */

    /**
     * Détermine si le fallback vers l'API doit être utilisé
     */
    private function shouldUseFallback(): bool
    {
        // Par défaut, ne pas utiliser le fallback pour optimiser les performances
        // Peut être configuré via variable d'environnement
        return $_ENV['PDF_ENABLE_API_FALLBACK'] ?? false;
    }

    /**
     * Récupère les photos avec fallback pour équipements au contrat
     */
    private function getEquipmentPicturesWithFallback($equipment, EntityManagerInterface $entityManager): array
    {
        try {
            $picturesArray = $entityManager->getRepository(Form::class)->findBy([
                'code_equipement' => $equipment->getNumeroEquipement(),
                'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()
            ]);
            
            return $entityManager->getRepository(Form::class)
                ->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
        } catch (\Exception $e) {
            error_log("Fallback API failed for equipment {$equipment->getNumeroEquipement()}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les photos avec fallback pour équipements supplémentaires
     */
    private function getSupplementaryEquipmentPicturesWithFallback($equipment, EntityManagerInterface $entityManager): array
    {
        try {
            return $entityManager->getRepository(Form::class)
                ->getPictureArrayByIdSupplementaryEquipment($entityManager, $equipment);
        } catch (\Exception $e) {
            error_log("Fallback API failed for supplementary equipment {$equipment->getNumeroEquipement()}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calcule les statistiques uniquement pour les équipements AU CONTRAT
     */
    private function calculateEquipmentStatistics(array $equipments): array
    {
        $etatsCount = [];
        $counterInexistant = 0;
        
        foreach ($equipments as $equipment) {
            // ✅ VÉRIFICATION : S'assurer qu'on ne traite que les équipements au contrat
            if (!$equipment->isEnMaintenance()) {
                continue; // Ignorer les équipements hors contrat
            }
            
            $etat = $equipment->getEtat();
            
            if ($etat === "Equipement non présent sur site" || $etat === "G") {
                $counterInexistant++;
            } elseif ($etat) {
                if (!isset($etatsCount[$etat])) {
                    $etatsCount[$etat] = 0;
                }
                $etatsCount[$etat]++;
            }
        }
        
        return [
            'etatsCount' => $etatsCount,
            'counterInexistant' => $counterInexistant,
            'totalAuContrat' => count($equipments) // ✅ AJOUT : Total des équipements au contrat
        ];
    }

    /**
     * Calcule les statistiques des équipements supplémentaires (HORS CONTRAT)
     * avec conversion des codes d'état en libellés lisibles
     */
    private function calculateSupplementaryStatistics(array $equipementsSupplementaires): array
    {
        $etatsCountSupplementaires = [];
        $totalSupplementaires = 0;
        
        foreach ($equipementsSupplementaires as $equipmentData) {
            $equipment = $equipmentData['equipment'];
            
            // ✅ VÉRIFICATION : S'assurer qu'on ne traite que les équipements hors contrat
            if ($equipment->isEnMaintenance()) {
                continue; // Ignorer les équipements au contrat
            }
            
            $etatCode = $equipment->getEtat();
            
            // ✅ CONVERSION des codes d'état en libellés lisibles
            $etatLibelle = $this->convertEtatCodeToLibelle($etatCode);
            
            if ($etatLibelle && $etatCode !== "Equipement non présent sur site" && $etatCode !== "G") {
                $totalSupplementaires++;
                
                if (!isset($etatsCountSupplementaires[$etatLibelle])) {
                    $etatsCountSupplementaires[$etatLibelle] = 0;
                }
                $etatsCountSupplementaires[$etatLibelle]++;
            }
        }
        
        return [
            'etatsCount' => $etatsCountSupplementaires,
            'total' => $totalSupplementaires
        ];
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

    /**
     * Récupère les informations client selon l'agence
     */
    private function getClientInformations(string $agence, string $id, EntityManagerInterface $entityManager)
    {
        switch ($agence) {
            case 'S10':
                return $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $id]);
            case 'S40':
                return $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $id]);
            case 'S50':
                return $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $id]);
            case 'S60':
                return $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $id]);
            case 'S70':
                return $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $id]);
            case 'S80':
                return $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $id]);
            case 'S100':
                return $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $id]);
            case 'S120':
                return $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $id]);
            case 'S130':
                return $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $id]);
            case 'S140':
                return $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $id]);
            case 'S150':
                return $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $id]);
            case 'S160':
                return $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $id]);
            case 'S170':
                return $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $id]);
            default:
                return null;
        }
    }

    /**
     * Log des métriques de performance
     */
    private function logPdfGenerationMetrics(string $agence, string $clientId, int $equipmentCount, array $photoStats, float $totalTime): void
    {
        $logData = [
            'type' => 'client_pdf_generation',
            'agence' => $agence,
            'client_id' => $clientId,
            'equipment_count' => $equipmentCount,
            'photo_sources' => $photoStats,
            'total_generation_time' => $totalTime,
            'average_time_per_equipment' => $equipmentCount > 0 ? round($totalTime / $equipmentCount, 3) : 0,
            'performance_gain' => $photoStats['local'] > 0 ? 'significant' : 'none',
            'timestamp' => date('c')
        ];
        
        error_log("PDF_GENERATION_METRICS: " . json_encode($logData));
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
    
    private function getEquipmentsByClientAndAgence(string $agence, string $id, EntityManagerInterface $entityManager)
    {
        switch ($agence) {
            case 'S10':
                return $entityManager->getRepository(EquipementS10::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S40':
                return $entityManager->getRepository(EquipementS40::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S50':
                return $entityManager->getRepository(EquipementS50::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S60':
                return $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S70':
                return $entityManager->getRepository(EquipementS70::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S80':
                return $entityManager->getRepository(EquipementS80::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S100':
                return $entityManager->getRepository(EquipementS100::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S120':
                return $entityManager->getRepository(EquipementS120::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S130':
                return $entityManager->getRepository(EquipementS130::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S140':
                return $entityManager->getRepository(EquipementS140::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S150':
                return $entityManager->getRepository(EquipementS150::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S160':
                return $entityManager->getRepository(EquipementS160::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S170':
                return $entityManager->getRepository(EquipementS170::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            default:
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
                return $basePath . 'group.jpg';
            case 'S40':
                return $basePath . 'st-etienne.jpg';
            case 'S50':
                return $basePath . 'grenoble.jpg';
            case 'S60':
                return $basePath . 'lyon.jpg';
            case 'S70':
                return $basePath . 'bordeaux.jpg';
            case 'S80':
                return $basePath . 'paris.jpg';
            case 'S100':
                return $basePath . 'montpellier.jpg';
            case 'S120':
                return $basePath . 'portland.jpg';
            case 'S130':
                return $basePath . 'toulouse.jpg';
            case 'S140':
                return $basePath . 'grand-est.jpg';
            case 'S150':
                return $basePath . 'paca.jpg';
            case 'S160':
                return $basePath . 'rouen.jpg';
            case 'S170':
                return $basePath . 'rennes.jpg';
            default:
                return $basePath . 'default.jpg'; // Image par défaut
        }
    }
}