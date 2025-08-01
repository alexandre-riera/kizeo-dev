<?php
// ===== MODIFICATION DU CONTRÔLEUR PDF COMPLET =====

namespace App\Controller;

use App\Service\PdfStorageService;
use App\Service\ShortLinkService;
use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class EnhancedEquipementPdfController extends AbstractController
{
    private PdfStorageService $pdfStorageService;
    private ShortLinkService $shortLinkService;
    private EmailService $emailService;
    private LoggerInterface $logger;

    public function __construct(
        PdfStorageService $pdfStorageService,
        ShortLinkService $shortLinkService,
        EmailService $emailService,
        LoggerInterface $logger
    ) {
        $this->pdfStorageService = $pdfStorageService;
        $this->shortLinkService = $shortLinkService;
        $this->emailService = $emailService;
        $this->logger = $logger;
    }

    /**
     * Version améliorée de la génération PDF avec toutes les fonctionnalités
     */
    #[Route('/client/equipements/pdf/enhanced/{agence}/{id}', name: 'client_equipements_pdf_enhanced')]
    public function generateEnhancedClientPdf(
        Request $request, 
        string $agence, 
        string $id, 
        EntityManagerInterface $entityManager
    ): Response {
        try {
            // 1. Validation des paramètres
            $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
            if (!in_array($agence, $validAgencies)) {
                throw new \InvalidArgumentException("Agence invalide: $agence");
            }

            // 2. Récupération des paramètres
            $annee = $request->query->get('clientAnneeFilter', date('Y'));
            $visite = $request->query->get('clientVisiteFilter', 'CEA');
            $sendEmail = $request->query->getBoolean('send_email', false);
            $clientEmail = $request->query->get('client_email');
            $autoStore = $request->query->getBoolean('auto_store', true);

            $this->logger->info("Génération PDF enhanced pour {$agence}/{$id}", [
                'annee' => $annee,
                'visite' => $visite,
                'send_email' => $sendEmail,
                'auto_store' => $autoStore
            ]);

            // 3. Vérifier si le PDF existe déjà en local
            $existingPdfPath = null;
            if ($autoStore) {
                $existingPdfPath = $this->pdfStorageService->getPdfPath($agence, $id, $annee, $visite);
            }

            // 4. Générer ou récupérer le PDF
            if ($existingPdfPath && file_exists($existingPdfPath)) {
                $this->logger->info("PDF existant trouvé: $existingPdfPath");
                $pdfContent = file_get_contents($existingPdfPath);
                $wasGenerated = false;
            } else {
                $this->logger->info("Génération d'un nouveau PDF");
                $pdfContent = $this->generatePdfContent($agence, $id, $annee, $visite, $entityManager);
                $wasGenerated = true;
                
                // Stockage automatique si activé
                if ($autoStore) {
                    $storedPath = $this->pdfStorageService->storePdf($agence, $id, $annee, $visite, $pdfContent);
                    $this->logger->info("PDF stocké: $storedPath");
                }
            }

            // 5. Création/récupération du lien court
            $downloadUrl = $this->generateUrl('pdf_download', [
                'agence' => $agence,
                'clientId' => $id,
                'annee' => $annee,
                'visite' => $visite
            ], true);

            $expiresAt = (new \DateTime())->modify('+30 days');
            $shortLink = $this->shortLinkService->createShortLink(
                $downloadUrl,
                $agence,
                $id,
                $annee,
                $visite,
                $expiresAt
            );

            $shortUrl = $this->shortLinkService->getShortUrl($shortLink->getShortCode());
            $this->logger->info("Lien court créé: $shortUrl");

            // 6. Envoi par email si demandé
            $emailResult = ['sent' => false, 'error' => null];
            if ($sendEmail && $clientEmail) {
                $clientInfo = $this->getClientInfo($agence, $id, $entityManager);
                
                $emailSent = $this->emailService->sendPdfLinkToClient(
                    $agence,
                    $clientEmail,
                    $clientInfo['nom'] ?? "Client $id",
                    $shortUrl,
                    $annee,
                    $visite
                );

                $emailResult = [
                    'sent' => $emailSent,
                    'error' => $emailSent ? null : 'Erreur lors de l\'envoi'
                ];

                $this->logger->info("Email " . ($emailSent ? 'envoyé' : 'échoué') . " à $clientEmail");
            }

            // 7. Préparer la réponse selon le type de requête
            if ($request->headers->get('Accept') === 'application/json' || $sendEmail) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'PDF ' . ($wasGenerated ? 'généré' : 'récupéré') . ' avec succès',
                    'data' => [
                        'agence' => $agence,
                        'client_id' => $id,
                        'annee' => $annee,
                        'visite' => $visite,
                        'pdf_generated' => $wasGenerated,
                        'pdf_stored' => $autoStore,
                        'short_url' => $shortUrl,
                        'short_code' => $shortLink->getShortCode(),
                        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                        'email' => $emailResult
                    ]
                ]);
            }

            // 8. Retourner le PDF directement
            $filename = "equipements_client_{$id}_{$agence}_{$annee}_{$visite}.pdf";
            
            return new Response($pdfContent, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"$filename\"",
                'X-PDF-Generated' => $wasGenerated ? 'true' : 'false',
                'X-PDF-Stored' => $autoStore ? 'true' : 'false',
                'X-Short-URL' => $shortUrl,
                'X-Short-Code' => $shortLink->getShortCode(),
                'Cache-Control' => 'private, max-age=3600'
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur génération PDF enhanced: " . $e->getMessage(), [
                'agence' => $agence,
                'client_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->headers->get('Accept') === 'application/json') {
                return new JsonResponse([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 500);
            }

            throw $e;
        }
    }

    /**
     * Endpoint pour envoyer un PDF existant par email
     */
    #[Route('/client/equipements/send-email/{agence}/{id}', name: 'send_pdf_email')]
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

            // Vérifier que le PDF existe ou le générer
            $pdfPath = $this->pdfStorageService->getPdfPath($agence, $id, $annee, $visite);
            if (!$pdfPath) {
                // Générer le PDF s'il n'existe pas
                $pdfContent = $this->generatePdfContent($agence, $id, $annee, $visite, $entityManager);
                $pdfPath = $this->pdfStorageService->storePdf($agence, $id, $annee, $visite, $pdfContent);
            }

            // Créer ou récupérer le lien court
            $downloadUrl = $this->generateUrl('pdf_download', [
                'agence' => $agence,
                'clientId' => $id,
                'annee' => $annee,
                'visite' => $visite
            ], true);

            $shortLink = $this->shortLinkService->createShortLink(
                $downloadUrl, $agence, $id, $annee, $visite,
                (new \DateTime())->modify('+30 days')
            );

            $shortUrl = $this->shortLinkService->getShortUrl($shortLink->getShortCode());

            // Envoyer l'email
            $clientInfo = $this->getClientInfo($agence, $id, $entityManager);
            $emailSent = $this->emailService->sendPdfLinkToClient(
                $agence,
                $clientEmail,
                $clientInfo['nom'] ?? "Client $id",
                $shortUrl,
                $annee,
                $visite
            );

            return new JsonResponse([
                'success' => $emailSent,
                'message' => $emailSent ? 'Email envoyé avec succès' : 'Erreur lors de l\'envoi',
                'short_url' => $shortUrl
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Méthodes privées...
    private function generatePdfContent(string $agence, string $id, string $annee, string $visite, EntityManagerInterface $entityManager): string
    {
        // Intégrer ici votre logique existante de génération PDF
        // Cette méthode doit retourner le contenu binaire du PDF
        
        // Exemple d'intégration avec votre code existant:
        // return $this->callExistingPdfGeneration($agence, $id, $annee, $visite, $entityManager);
        
        return "PDF_CONTENT_PLACEHOLDER"; // À remplacer par votre implémentation
    }

    private function getClientInfo(string $agence, string $id, EntityManagerInterface $entityManager): array
    {
        // Adapter selon votre structure d'entités
        switch ($agence) {
            case 'S50':
                $contactEntity = "App\\Entity\\ContactS50";
                break;
            case 'S140':
                $contactEntity = "App\\Entity\\ContactS140";
                break;
            // Ajouter les autres agences...
            default:
                $contactEntity = "App\\Entity\\Contact{$agence}";
        }

        try {
            $contact = $entityManager->getRepository($contactEntity)->findOneBy(['id_contact' => $id]);
            
            return [
                'nom' => $contact ? $contact->getRaisonSociale() : "Client $id",
                'email' => $contact ? $contact->getEmail() : null,
                'adresse' => $contact ? $contact->getAdresse() : null
            ];
        } catch (\Exception $e) {
            $this->logger->warning("Impossible de récupérer les infos client $id pour l'agence $agence: " . $e->getMessage());
            return ['nom' => "Client $id"];
        }
    }
}