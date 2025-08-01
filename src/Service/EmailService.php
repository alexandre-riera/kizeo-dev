<?php
// ===== 5. SERVICE D'ENVOI D'EMAIL =====

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

class EmailService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private array $agencyEmails;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
        
        // Configuration des emails par agence (à adapter selon vos besoins)
        $this->agencyEmails = [
            'S50' => 'grenoble@somafi-group.fr',
            'S140' => 'epinal@somafi-group.fr',
            // Ajouter les autres agences...
        ];
    }

    /**
     * Envoie le lien PDF par email au client
     */
    public function sendPdfLinkToClient(
        string $agence,
        string $clientEmail,
        string $clientName,
        string $shortUrl,
        string $annee,
        string $visite
    ): bool {
        try {
            $senderEmail = $this->agencyEmails[$agence] ?? 'noreply@somafi-group.fr';
            
            $email = (new Email())
                ->from($senderEmail)
                ->to($clientEmail)
                ->subject("Rapport d'équipements - {$clientName} - {$annee}")
                ->html($this->buildEmailTemplate($clientName, $shortUrl, $agence, $annee, $visite));

            $this->mailer->send($email);
            
            $this->logger->info("Email envoyé avec succès à {$clientEmail} pour l'agence {$agence}");
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur envoi email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Template HTML pour l'email
     */
    private function buildEmailTemplate(string $clientName, string $shortUrl, string $agence, string $annee, string $visite): string
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #1a365d; color: white; padding: 20px; text-align: center;'>
                <h1>SOMAFI Grenoble</h1>
                <p>Rapport d'équipements</p>
            </div>
            
            <div style='padding: 30px; background-color: #f8f9fa;'>
                <h2>Bonjour {$clientName},</h2>
                
                <p>Nous avons le plaisir de vous transmettre le rapport d'équipements suite à notre visite de maintenance.</p>
                
                <div style='background-color: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3>Détails de la visite :</h3>
                    <ul>
                        <li><strong>Année :</strong> {$annee}</li>
                        <li><strong>Type de visite :</strong> {$visite}</li>
                        <li><strong>Agence :</strong> {$agence}</li>
                    </ul>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$shortUrl}' 
                       style='background-color: #28a745; color: white; padding: 15px 30px; 
                              text-decoration: none; border-radius: 5px; font-weight: bold;
                              display: inline-block;'>
                        📄 Télécharger le rapport PDF
                    </a>
                </div>
                
                <div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>⚠️ Important :</strong></p>
                    <ul>
                        <li>Ce lien est sécurisé et personnel</li>
                        <li>Il restera valide pendant 30 jours</li>
                        <li>Contactez-nous si vous rencontrez des difficultés</li>
                    </ul>
                </div>
                
                <p>Pour toute question concernant ce rapport, n'hésitez pas à nous contacter.</p>
                
                <p>Cordialement,<br>
                L'équipe SOMAFI {$agence}</p>
            </div>
            
            <div style='background-color: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                <p>SOMAFI Grenoble | Email automatique - Ne pas répondre</p>
            </div>
        </body>
        </html>";
    }
}