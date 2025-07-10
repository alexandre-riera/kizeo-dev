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
use App\Entity\EquipementS100;
use App\Entity\EquipementS120;
use App\Entity\EquipementS130;
use App\Entity\EquipementS140;
use App\Entity\EquipementS150;
use App\Entity\EquipementS160;
use App\Entity\EquipementS170;
use App\Service\PdfGenerator;
use App\Service\ImageStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class EquipementPdfController extends AbstractController
{
    private ImageStorageService $imageStorageService;
    private PdfGenerator $pdfGenerator;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        ImageStorageService $imageStorageService,
        PdfGenerator $pdfGenerator,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->imageStorageService = $imageStorageService;
        $this->pdfGenerator = $pdfGenerator;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }
    
    /**
     * Génération PDF d'un équipement unique
     */
    #[Route('/equipement/pdf/{agence}/{id}', name: 'equipement_pdf_single')]
    public function generateSingleEquipementPdf(string $agence, string $id, EntityManagerInterface $entityManager): Response
    {
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
     * Génération PDF optimisée avec images en cache
     */
    #[Route('/client/equipements/pdf/{agence}/{id}', name: 'client_equipements_pdf')]
    public function generateClientEquipementsPdf(Request $request, string $agence, string $id, EntityManagerInterface $entityManager): Response
    {
        try {
            // Récupérer les filtres
            $clientAnneeFilter = $request->query->get('clientAnneeFilter', '');
            $clientVisiteFilter = $request->query->get('clientVisiteFilter', '');
            
            // Récupérer les équipements
            $equipments = $this->getEquipmentsByClientAndAgence($agence, $id, $entityManager);
            
            if (empty($equipments)) {
                throw $this->createNotFoundException('Aucun équipement trouvé pour ce client');
            }
            
            // Appliquer les filtres
            $filteredEquipments = $this->applyFilters($equipments, $clientAnneeFilter, $clientVisiteFilter);
            
            // Préparer les données avec images optimisées
            $equipmentsWithPictures = $this->prepareEquipmentsWithOptimizedImages($filteredEquipments, $entityManager);
            
            // Récupérer les informations client
            $clientSelectedInformations = $this->getClientInformations($agence, $id, $entityManager);
            
            // Calculer les statistiques
            $statistiques = $this->calculateStatistics($equipmentsWithPictures);
            
            // Séparer les équipements par type
            $equipementsSupplementaires = array_filter($equipmentsWithPictures, function($equipement) {
                return !$equipement['equipment']->isEnMaintenance();
            });
            
            $equipementsNonPresents = array_filter($equipmentsWithPictures, function($equipement) {
                $etat = $equipement['equipment']->getEtat();
                return $etat === "Equipement non présent sur site" || $etat === "G";
            });
            
            // URL de l'image d'agence
            $imageUrl = $this->getImageUrlForAgency($agence);
            
            // Générer le HTML
            $html = $this->renderView('pdf/equipements.html.twig', [
                'equipmentsWithPictures' => $equipmentsWithPictures,
                'equipementsSupplementaires' => $equipementsSupplementaires,
                'equipementsNonPresents' => $equipementsNonPresents,
                'clientId' => $id,
                'agence' => $agence,
                'imageUrl' => $imageUrl,
                'clientAnneeFilter' => $clientAnneeFilter,
                'clientVisiteFilter' => $clientVisiteFilter,
                'statistiques' => $statistiques,
                'clientSelectedInformations' => $clientSelectedInformations,
                'isFiltered' => !empty($clientAnneeFilter) || !empty($clientVisiteFilter)
            ]);
            
            // Générer le nom de fichier
            $filename = $this->generateOptimizedFilename($id, $agence, $clientAnneeFilter, $clientVisiteFilter);
            
            // Générer le PDF
            $pdfContent = $this->pdfGenerator->generatePdf($html, $filename);
            
            return new Response(
                $pdfContent,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "inline; filename=\"$filename\""
                ]
            );
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur génération PDF: " . $e->getMessage());
            throw new \RuntimeException("Erreur lors de la génération du PDF: " . $e->getMessage());
        }
    }

    /**
     * API pour s'assurer que les images sont disponibles avant génération PDF
     */
    #[Route('/api/equipements/ensure-images/{agence}/{clientId}', name: 'api_ensure_images_before_pdf', methods: ['POST'])]
    public function ensureImagesBeforePdfGeneration(string $agence, string $clientId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?? [];
            $forceDownload = $data['force'] ?? false;
            
            $equipments = $this->getEquipmentsByClientAndAgence($agence, $clientId, $entityManager);
            
            if (empty($equipments)) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Aucun équipement trouvé pour ce client'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $downloadResults = [
                'total_equipments' => count($equipments),
                'images_downloaded' => 0,
                'images_already_exist' => 0,
                'errors' => 0
            ];
            
            foreach ($equipments as $equipment) {
                try {
                    $agenceCode = $this->extractAgenceFromEntity($equipment);
                    $raisonSociale = $equipment->getRaisonSociale();
                    $annee = $this->extractAnneeFromEquipement($equipment);
                    $typeVisite = $this->extractTypeVisiteFromEquipement($equipment);
                    $codeEquipement = $equipment->getNumeroEquipement();
                    
                    // Vérifier si l'image existe déjà
                    $imageExists = $this->imageStorageService->imageExists(
                        $agenceCode, $raisonSociale, $annee, $typeVisite, $codeEquipement
                    );
                    
                    if ($imageExists && !$forceDownload) {
                        $downloadResults['images_already_exist']++;
                        continue;
                    }
                    
                    // Tenter de télécharger l'image
                    $downloadSuccess = $this->downloadImageForEquipment($equipment, $entityManager);
                    
                    if ($downloadSuccess) {
                        $downloadResults['images_downloaded']++;
                    } else {
                        $downloadResults['errors']++;
                    }
                    
                } catch (\Exception $e) {
                    $downloadResults['errors']++;
                    $this->logger->error("Erreur téléchargement image pour équipement {$equipment->getNumeroEquipement()}: " . $e->getMessage());
                }
            }
            
            return new JsonResponse([
                'status' => 'success',
                'message' => 'Vérification des images terminée',
                'results' => $downloadResults,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * API pour obtenir le statut des images d'un client
     */
    #[Route('/api/equipements/images-status/{agence}/{clientId}', name: 'api_images_status', methods: ['GET'])]
    public function getImagesStatus(string $agence, string $clientId, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $equipments = $this->getEquipmentsByClientAndAgence($agence, $clientId, $entityManager);
            
            $status = [
                'total_equipments' => count($equipments),
                'images_available' => 0,
                'images_missing' => 0,
                'coverage_percentage' => 0
            ];
            
            foreach ($equipments as $equipment) {
                $agenceCode = $this->extractAgenceFromEntity($equipment);
                $raisonSociale = $equipment->getRaisonSociale();
                $annee = $this->extractAnneeFromEquipement($equipment);
                $typeVisite = $this->extractTypeVisiteFromEquipement($equipment);
                $codeEquipement = $equipment->getNumeroEquipement();
                
                $imageExists = $this->imageStorageService->imageExists(
                    $agenceCode, $raisonSociale, $annee, $typeVisite, $codeEquipement
                );
                
                if ($imageExists) {
                    $status['images_available']++;
                } else {
                    $status['images_missing']++;
                }
            }
            
            $status['coverage_percentage'] = $status['total_equipments'] > 0 
                ? round(($status['images_available'] / $status['total_equipments']) * 100, 2)
                : 0;
            
            return new JsonResponse([
                'status' => 'success',
                'data' => $status,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================== MÉTHODES PRIVÉES ====================

    /**
     * Prépare les équipements avec images optimisées
     */
    private function prepareEquipmentsWithOptimizedImages(array $equipments, EntityManagerInterface $entityManager): array
    {
        $equipmentsWithPictures = [];
        
        foreach ($equipments as $equipment) {
            $agence = $this->extractAgenceFromEntity($equipment);
            $raisonSociale = $equipment->getRaisonSociale();
            $annee = $this->extractAnneeFromEquipement($equipment);
            $typeVisite = $this->extractTypeVisiteFromEquipement($equipment);
            $codeEquipement = $equipment->getNumeroEquipement();
            
            // Vérifier si l'image existe localement
            $imagePath = $this->imageStorageService->getImagePath(
                $agence, $raisonSociale, $annee, $typeVisite, $codeEquipement
            );
            
            $picturesData = [];
            if ($imagePath) {
                // Image trouvée localement
                $picturesData = [
                    'url' => $this->imageStorageService->getImageUrl(
                        $agence, $raisonSociale, $annee, $typeVisite, $codeEquipement
                    ),
                    'path' => $imagePath,
                    'available' => true
                ];
            } else {
                // Essayer de récupérer depuis Kizeo (logique existante)
                $picturesData = $this->getExistingPicturesData($equipment, $entityManager);
            }
            
            $equipmentsWithPictures[] = [
                'equipment' => $equipment,
                'picturesData' => $picturesData
            ];
        }
        
        return $equipmentsWithPictures;
    }

    /**
     * Récupère les données d'images existantes (votre logique actuelle)
     */
    private function getExistingPicturesData($equipment, EntityManagerInterface $entityManager): array
    {
        try {
            if ($equipment->isEnMaintenance()) {
                // Équipements au contrat
                $picturesArray = $entityManager->getRepository(Form::class)->findBy([
                    'code_equipement' => $equipment->getNumeroEquipement(),
                    'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()
                ]);
                
                if (!empty($picturesArray)) {
                    return $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment(
                        $picturesArray, $entityManager, $equipment
                    );
                }
            } else {
                // Équipements supplémentaires
                return $entityManager->getRepository(Form::class)->getPictureArrayByIdSupplementaryEquipment(
                    $entityManager, $equipment
                );
            }
        } catch (\Exception $e) {
            $this->logger->warning("Erreur récupération image existante: " . $e->getMessage());
        }
        
        return ['available' => false];
    }

    /**
     * Télécharge une image pour un équipement spécifique
     */
    private function downloadImageForEquipment($equipment, EntityManagerInterface $entityManager): bool
    {
        try {
            $picturesData = $this->getExistingPicturesData($equipment, $entityManager);
            
            if (empty($picturesData) || !isset($picturesData['url'])) {
                return false;
            }
            
            // Télécharger l'image
            $response = $this->httpClient->request('GET', $picturesData['url'], [
                'headers' => [
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"] ?? '',
                ],
                'timeout' => 30
            ]);
            
            if ($response->getStatusCode() === 200) {
                $imageContent = $response->getContent();
                
                // Stocker l'image
                $agence = $this->extractAgenceFromEntity($equipment);
                $raisonSociale = $equipment->getRaisonSociale();
                $annee = $this->extractAnneeFromEquipement($equipment);
                $typeVisite = $this->extractTypeVisiteFromEquipement($equipment);
                $codeEquipement = $equipment->getNumeroEquipement();
                
                $this->imageStorageService->storeImage(
                    $agence, $raisonSociale, $annee, $typeVisite, $codeEquipement, $imageContent
                );
                
                return true;
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur téléchargement image: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Applique les filtres sur les équipements
     */
    private function applyFilters(array $equipments, string $clientAnneeFilter, string $clientVisiteFilter): array
    {
        if (empty($clientAnneeFilter) && empty($clientVisiteFilter)) {
            return $equipments;
        }
        
        return array_filter($equipments, function ($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
            $match = true;
            
            if (!empty($clientAnneeFilter)) {
                $equipmentYear = $equipment->getDerniereVisite() ? 
                    $equipment->getDerniereVisite()->format('Y') : 
                    date('Y');
                $match = $match && ($equipmentYear === $clientAnneeFilter);
            }
            
            if (!empty($clientVisiteFilter)) {
                $equipmentVisite = $equipment->getVisite() ?? '';
                $match = $match && ($equipmentVisite === $clientVisiteFilter);
            }
            
            return $match;
        });
    }

    /**
     * Calcule les statistiques des équipements
     */
    private function calculateStatistics(array $equipmentsWithPictures): array
    {
        $etatsCount = [];
        $totalEquipments = count($equipmentsWithPictures);
        $withImages = 0;
        
        foreach ($equipmentsWithPictures as $equipement) {
            $etat = $equipement['equipment']->getEtat();
            
            if ($etat && $etat !== "Equipement non présent sur site" && $etat !== "G") {
                if (!isset($etatsCount[$etat])) {
                    $etatsCount[$etat] = 0;
                }
                $etatsCount[$etat]++;
            }
            
            if (isset($equipement['picturesData']['available']) && $equipement['picturesData']['available']) {
                $withImages++;
            }
        }
        
        return [
            'total' => $totalEquipments,
            'withImages' => $withImages,
            'withoutImages' => $totalEquipments - $withImages,
            'etatsCount' => $etatsCount
        ];
    }

    /**
     * Génère le nom de fichier optimisé
     */
    private function generateOptimizedFilename(string $clientId, string $agence, string $clientAnneeFilter, string $clientVisiteFilter): string
    {
        $filename = "equipements_client_{$clientId}_{$agence}";
        
        if (!empty($clientAnneeFilter) || !empty($clientVisiteFilter)) {
            $filename .= '_filtered';
            if (!empty($clientAnneeFilter)) {
                $filename .= '_' . $clientAnneeFilter;
            }
            if (!empty($clientVisiteFilter)) {
                $filename .= '_' . str_replace(' ', '_', $clientVisiteFilter);
            }
        }
        
        return $filename . '.pdf';
    }

    /**
     * Récupère les informations d'un client
     */
    private function getClientInformations(string $agence, string $id, EntityManagerInterface $entityManager): array
    {
        try {
            $contactEntity = $this->getContactEntityByAgence($agence);
            if (!$contactEntity) {
                return ['raisonSociale' => 'Client inconnu'];
            }
            
            $contact = $entityManager->getRepository($contactEntity)->find($id);
            
            if ($contact) {
                return [
                    'raisonSociale' => $contact->getRaisonSociale() ?? 'Non renseigné',
                    'adresse' => $contact->getAdresse() ?? '',
                    'ville' => $contact->getVille() ?? '',
                    'codePostal' => $contact->getCodePostal() ?? ''
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Erreur récupération informations client: " . $e->getMessage());
        }
        
        return ['raisonSociale' => 'Client inconnu'];
    }

    /**
     * Mapping des entités Contact par agence
     */
    private function getContactEntityByAgence(string $agence): ?string
    {
        $contactEntities = [
            'S10' => ContactS10::class,
            'S40' => ContactS40::class,
            'S50' => ContactS50::class,
            'S60' => ContactS60::class,
            'S70' => ContactS70::class,
            'S80' => ContactS80::class,
            'S100' => ContactS100::class,
            'S120' => ContactS120::class,
            'S130' => ContactS130::class,
            'S140' => ContactS140::class,
            'S150' => ContactS150::class,
            'S160' => ContactS160::class,
            'S170' => ContactS170::class,
        ];
        
        return $contactEntities[$agence] ?? null;
    }

    /**
     * Récupère un équipement par agence et ID
     */
    private function getEquipmentByAgence(string $agence, string $id, EntityManagerInterface $entityManager)
    {
        $equipmentEntities = [
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
        
        $entityClass = $equipmentEntities[$agence] ?? null;
        
        return $entityClass ? $entityManager->getRepository($entityClass)->findOneBy(['id' => $id]) : null;
    }
    
    /**
     * Récupère les équipements d'un client par agence
     */
    private function getEquipmentsByClientAndAgence(string $agence, string $id, EntityManagerInterface $entityManager): array
    {
        $equipmentEntities = [
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
        
        $entityClass = $equipmentEntities[$agence] ?? null;
        
        return $entityClass ? 
            $entityManager->getRepository($entityClass)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']) : 
            [];
    }

    /**
     * URL de l'image d'agence (votre logique existante)
     */
    private function getImageUrlForAgency(string $agencyName): string
    {
        $basePath = 'https://www.pdf.somafi-group.fr/background/';

        $images = [
            'S10' => 'group.jpg',
            'S40' => 'st-etienne.jpg',
            'S50' => 'grenoble.jpg',
            'S60' => 'lyon.jpg',
            'S70' => 'bordeaux.jpg',
            'S80' => 'paris.jpg',
            'S100' => 'montpellier.jpg',
            'S120' => 'portland.jpg',
            'S130' => 'toulouse.jpg',
            'S140' => 'grand-est.jpg',
            'S150' => 'paca.jpg',
            'S160' => 'rouen.jpg',
            'S170' => 'rennes.jpg',
        ];

        return $basePath . ($images[$agencyName] ?? 'default.jpg');
    }

    /**
     * Extrait l'agence depuis une entité équipement
     */
    private function extractAgenceFromEntity($equipment): string
    {
        $className = get_class($equipment);
        if (preg_match('/EquipementS(\d+)/', $className, $matches)) {
            return 'S' . $matches[1];
        }
        return 'UNKNOWN';
    }

    /**
     * Extrait l'année depuis un équipement
     */
    private function extractAnneeFromEquipement($equipment): string
    {
        $date = $equipment->getDerniereVisite() ?? $equipment->getCreatedAt() ?? new \DateTime();
        return $date->format('Y');
    }

    /**
     * Extrait le type de visite depuis un équipement
     */
    private function extractTypeVisiteFromEquipement($equipment): string
    {
        return $equipment->getVisite() ?? 'CE1';
    }
}