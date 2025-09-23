<?php

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
use App\Service\EmailService;
use App\Service\PdfGenerator;
use App\Service\ShortLinkService;
use App\Service\PdfStorageService;
use App\Service\ImageStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class EquipementPdfController extends AbstractController
{
    public function __construct(
        private PdfGenerator $pdfGenerator,
        private PdfStorageService $pdfStorageService,
        private ShortLinkService $shortLinkService,
        private EmailService $emailService,
        private ImageStorageService $imageStorageService
    ) {}

    /**
     * Route pour générer le PDF des équipements d'un client
     * Cette route correspond à celle utilisée dans le template home/index.html.twig
     * Accepte les paramètres via GET (liens directs depuis le template)
     */
    #[Route('/equipements/pdf', name: 'client_equipements_pdf', methods: ['GET'])]
    public function generateClientEquipementsPdf(Request $request, EntityManagerInterface $entityManager): Response
    {
        try {
            // Récupérer les paramètres depuis la requête GET (lien direct)
            $agence = $request->query->get('agence');
            $clientId = $request->query->get('id');
            $clientAnneeFilter = $request->query->get('clientAnneeFilter');
            $clientVisiteFilter = $request->query->get('clientVisiteFilter');
            
            // Validation des paramètres obligatoires
            if (!$agence || !$clientId) {
                throw $this->createNotFoundException('Paramètres agence et id client requis');
            }

            // Utiliser les valeurs par défaut si les filtres ne sont pas fournis
            $annee = $clientAnneeFilter ?: date('Y');
            $visite = $clientVisiteFilter ?: 'CE1';

            // Appeler directement la méthode de génération de PDF
            return $this->generateClientCompletePdf($agence, $clientId, $annee, $visite, $entityManager);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la génération du PDF: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Génère un PDF pour un client complet avec première page et équipements
     */
    #[Route('/equipement/pdf/client/{agence}/{clientId}/{annee}/{visite}', name: 'equipement_pdf_client_complete')]
    public function generateClientCompletePdf(
        string $agence,
        string $clientId,
        string $annee,
        string $visite,
        EntityManagerInterface $entityManager
    ): Response {
        try {
            // Récupérer les données du client
            $client = $this->getClientByAgence($agence, $clientId, $entityManager);
            if (!$client) {
                throw $this->createNotFoundException('Client non trouvé');
            }

            // Récupérer tous les équipements du client
            $equipements = $this->getEquipmentsByClientId($agence, $clientId, $entityManager);
            
            // Préparer les données pour le template
            $clientData = $this->formatClientData($client);
            $equipementsData = $this->formatEquipementsData($equipements, $entityManager);
            $stats = $this->calculateStats($equipementsData);
            // Générer le PDF
            $html = $this->renderView('pdf/equipements.html.twig', [
                'titre' => 'COMPTE RENDU D\'ENTRETIEN',
                'client' => $clientData,
                'equipements' => $equipementsData,
                'stats' => $stats,
                'agence' => $this->getAgenceNameFromCode($agence)
            ]);

            $filename = sprintf('rapport_client_%s_%s_%s.pdf', 
                $clientId, 
                $annee, 
                $visite
            );

            // Stocker le PDF
            $pdfContent = $this->pdfGenerator->generatePdf($html);
            $this->pdfStorageService->storePdf($agence, $clientId, $annee, $visite, $pdfContent);

            return new Response($pdfContent, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la génération du PDF: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Envoie le PDF par email et retourne un lien court
     */
    #[Route('/equipement/pdf/email/{agence}/{clientId}/{annee}/{visite}', name: 'equipement_pdf_email')]
    public function sendPdfByEmail(
        string $agence,
        string $clientId,
        string $annee,
        string $visite,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            // Vérifier si le PDF existe, sinon le générer
            if (!$this->pdfStorageService->getPdfPath($agence, $clientId, $annee, $visite)) {
                $this->generateClientCompletePdf($agence, $clientId, $annee, $visite, $entityManager);
            }

            // Créer un lien court pour le téléchargement
            $downloadUrl = $this->generateUrl('pdf_download', [
                'agence' => $agence,
                'clientId' => $clientId,
                'annee' => $annee,
                'visite' => $visite
            ], true);

            $shortLink = $this->shortLinkService->createShortLink(
                $downloadUrl,
                $agence,
                $clientId,
                $annee,
                $visite
            );

            // Récupérer l'URL complète du lien court
            $shortUrl = $this->shortLinkService->getShortUrl($shortLink->getShortCode());

            // Récupérer les informations du client pour l'email
            $client = $this->getClientByAgence($agence, $clientId, $entityManager);
            
            // Envoyer l'email
            $this->emailService->sendPdfLinkToClient(
                $agence,
                $client->getEmail() ?? 'client@example.com',
                $client->getRaisonSociale() ?? 'Client',
                $shortUrl,
                $annee,
                $visite
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'PDF généré et email envoyé avec succès',
                'shortUrl' => $shortUrl
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'envoi: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
        $filename = "rapport_client_{$clientId}_{$annee}_{$visite}.pdf";
        
        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    // ===== MÉTHODES PRIVÉES =====

    /**
     * Récupère un client selon l'agence
     */
    private function getClientByAgence(string $agence, string $clientId, EntityManagerInterface $entityManager)
    {
        return match ($agence) {
            'S10' => $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $clientId]),
            'S40' => $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $clientId]),
            'S50' => $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $clientId]),
            'S60' => $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $clientId]),
            'S70' => $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $clientId]),
            'S80' => $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $clientId]),
            'S100' => $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $clientId]),
            'S120' => $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $clientId]),
            'S130' => $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $clientId]),
            'S140' => $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $clientId]),
            'S150' => $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $clientId]),
            'S160' => $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $clientId]),
            'S170' => $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $clientId]),
            default => null,
        };
    }

    /**
     * Récupère tous les équipements d'un client selon l'agence
     */
    private function getEquipmentsByClientId(string $agence, string $clientId, EntityManagerInterface $entityManager): array
    {
        return match ($agence) {
            'S10' => $entityManager->getRepository(EquipementS10::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S40' => $entityManager->getRepository(EquipementS40::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S50' => $entityManager->getRepository(EquipementS50::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S60' => $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S70' => $entityManager->getRepository(EquipementS70::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S80' => $entityManager->getRepository(EquipementS80::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S100' => $entityManager->getRepository(EquipementS100::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S120' => $entityManager->getRepository(EquipementS120::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S130' => $entityManager->getRepository(EquipementS130::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S140' => $entityManager->getRepository(EquipementS140::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S150' => $entityManager->getRepository(EquipementS150::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S160' => $entityManager->getRepository(EquipementS160::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            'S170' => $entityManager->getRepository(EquipementS170::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']),
            default => [],
        };
    }

    /**
     * Formate les données client pour le template
     */
    private function formatClientData($client): array
    {
        if (!$client) {
            return ['nom' => 'Client non trouvé'];
        }

        return [
            'nom' => $client->getRaisonSociale() ?? 'Non renseigné',
            'adresse' => [
                'ligne1' => $client->getAdressep1() ?? '',
                'ligne2' => $client->getAdressep2() ?? '',
                'codePostal' => $client->getCpostalp() ?? '',
                'ville' => $client->getVillep() ?? '',
            ]
        ];
    }

    /**
     * Formate les données équipements pour le template
     */
    private function formatEquipementsData(array $equipements, EntityManagerInterface $entityManager): array
    {
        $formattedEquipements = [];

        foreach ($equipements as $equipment) {
            // Récupérer les photos depuis les formulaires
            $photos = $entityManager->getRepository(Form::class)->findBy([
                'code_equipement' => $equipment->getNumeroEquipement(),
                'id_contact' => $equipment->getIdContact()
            ]);
            
            // MODIFICATION : Utiliser la nouvelle architecture pour récupérer les images
            $photoEquipement = null;
            if (!empty($photos)) {
                $form = $photos[0];
                $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
                $typeVisite = $equipment->getVisite();
                
                // Récupérer l'image avec la nouvelle architecture
                $photoEquipement = $this->imageStorageService->getGeneralImageUrl(
                    $equipment->getCodeAgence(),
                    $equipment->getIdContact(),  // Utiliser id_contact
                    $anneeVisite,
                    $typeVisite,
                    $equipment->getNumeroEquipement()
                );
            }
            
            $formattedEquipements[] = [
                'numero' => $equipment->getNumeroEquipement() ?? 'N/A',
                'type' => $equipment->getTypeEquipement() ?? 'N/A',
                'marque' => $equipment->getMarque() ?? 'Non renseigné',
                'modele' => $equipment->getModele() ?? 'Non renseigné',
                'etat' => $equipment->getEtat() ?? 'Non renseigné',
                'anomalies' => $equipment->getAnomalies() ?? '',
                'photo' => $photoEquipement,
                'mise_en_service' => $equipment->getMiseEnService() ?? '',
                'repere_site_client' => $equipment->getRepereSiteClient() ?? ''
            ];
        }

        return $formattedEquipements;
    }

    /**
     * Détermine le statut d'un équipement
     */
    private function determineEquipmentStatus($equipment): string
    {
        // À adapter selon ta logique métier
        if (method_exists($equipment, 'getEtat')) {
            $etat = $equipment->getEtat();
            if (str_contains(strtolower($etat), 'urgent') || str_contains(strtolower($etat), 'arrêt')) {
                return 'urgent';
            }
            if (str_contains(strtolower($etat), 'attention') || str_contains(strtolower($etat), 'préventif')) {
                return 'attention';
            }
        }
        return 'bon';
    }

    /**
     * Récupère le libellé du statut
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'urgent' => 'Travaux urgent ou à l\'arrêt',
            'attention' => 'Travaux préventifs',
            'bon' => 'Bon état',
            default => 'État non défini',
        };
    }

    /**
     * Récupère les anomalies d'un équipement
     */
    private function getEquipmentAnomalies($equipment): array
    {
        $anomalies = [];

        if (method_exists($equipment, 'getAnomalies') && $equipment->getAnomalies()) {
            $anomalies[] = ['description' => $equipment->getAnomalies()];
        }
        
        return $anomalies;
    }

    /**
     * Calcule les statistiques pour la première page
     */
    private function calculateStats(array $equipements): array
    {
        $stats = [
            'equipements_contrat' => count($equipements),
            'equipements_inexistants' => 0,
            'equipements_supplementaires' => 0,
            'par_statut' => []
        ];

        // Compter par statut
        $statutsCount = [];
        foreach ($equipements as $eq) {
            $statut = $eq['statut'] ?? 'bon';
            $statutsCount[$statut] = ($statutsCount[$statut] ?? 0) + 1;
        }

        // Convertir en format pour le template
        foreach ($statutsCount as $statut => $nombre) {
            $stats['par_statut'][] = [
                'type' => $statut,
                'libelle' => $this->getStatusLabel($statut),
                'nombre' => $nombre
            ];
        }

        return $stats;
    }

    /**
     * Convertit le code agence en nom pour l'image de fond
     */
    private function getAgenceNameFromCode(string $agenceCode): string
    {
        return match ($agenceCode) {
            'S10' => 'st-etienne',
            'S40' => 'lyon',
            'S50' => 'grenoble',
            'S60' => 'bordeaux',
            'S70' => 'toulouse',
            'S80' => 'montpellier',
            'S100' => 'paca',
            'S120' => 'paris',
            'S130' => 'rouen',
            'S140' => 'rennes',
            'S150' => 'grand-est',
            'S160' => 'portland',
            'S170' => 'group',
            default => 'st-etienne',
        };
    }

    /**
     * Log personnalisé pour le débogage
     */
    private function customLog(string $message): void
    {
        error_log("[EquipementPdfController] " . $message);
    }
}