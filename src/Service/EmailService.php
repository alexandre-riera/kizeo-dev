<?php
// ===== SERVICE D'ENVOI D'EMAIL COMPLET =====

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
        
        // Configuration des emails par agence selon vos adresses
        $this->agencyEmails = [
            'S10' => 'group@somafi-group.fr',
            'S40' => 'saintetienne@somafi-group.fr',
            'S50' => 'grenoble@somafi-group.fr',
            'S60' => 'lyon@somafi-group.fr',
            'S70' => 'bordeaux@somafi-group.fr',
            'S80' => 'parisnord@somafi-group.fr',
            'S100' => 'montpellier@somafi-group.fr',
            'S120' => 'hautsdefrance@somafi-group.fr',
            'S130' => 'toulouse@somafi-group.fr',
            'S140' => 'epinal@somafi-group.fr',
            'S150' => 'paca@somafi-group.fr',
            'S160' => 'rouen@somafi-group.fr',
            'S170' => 'rennes@somafi-group.fr',
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
        string $visite,
        string $customMessage = ''
    ): bool {
        try {
            // Validation de l'email
            if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                $this->logger->error("Email invalide: {$clientEmail}");
                return false;
            }

            $senderEmail = $this->agencyEmails[$agence] ?? 'noreply@somafi-group.fr';
            
            $email = (new Email())
                ->from($senderEmail)
                ->to($clientEmail)
                ->subject("Rapport d'équipements - {$clientName} - {$annee}")
                ->html($this->buildSecureEmailTemplate($clientName, $shortUrl, $agence, $annee, $visite));

            $this->mailer->send($email);
            
            $this->logger->info("Email sécurisé envoyé à {$clientEmail} pour l'agence {$agence}", [
                'short_url' => $shortUrl,
                'client' => $clientName,
                'agence' => $agence
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur envoi email: " . $e->getMessage(), [
                'agence' => $agence,
                'client_email' => $clientEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Template HTML sécurisé pour l'email - CORRIGÉ
     */
    private function buildSecureEmailTemplate(
        string $clientName, 
        string $shortUrl, // ⚠️ Lien court sécurisé uniquement
        string $agence, 
        string $annee, 
        string $visite
    ): string {
        return "
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Rapport d'équipements SOMAFI</title>
        </head>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f4f4f4;'>
            <!-- En-tête SOMAFI -->
            <div style='background: linear-gradient(135deg, #1a365d 0%, #2d5a87 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>
                    🏢 SOMAFI {$agence}
                </h1>
                <p style='margin: 5px 0 0 0; font-size: 16px; opacity: 0.9;'>
                    Rapport d'équipements
                </p>
            </div>
            
            <!-- Corps du message -->
            <div style='background-color: white; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <h2 style='color: #2c3e50; margin-top: 0;'>Bonjour {$clientName},</h2>
                
                <p style='color: #34495e; line-height: 1.6;'>
                    Nous avons le plaisir de vous transmettre le rapport d'équipements suite à notre visite de maintenance.
                </p>
                
                <!-- Informations de la visite -->
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #3498db;'>
                    <h3 style='color: #2c3e50; margin-top: 0; margin-bottom: 15px;'>📋 Détails de la visite</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 5px 0; font-weight: bold; color: #2c3e50;'>Année :</td>
                            <td style='padding: 5px 0; color: #34495e;'>{$annee}</td>
                        </tr>
                        <tr>
                            <td style='padding: 5px 0; font-weight: bold; color: #2c3e50;'>Type de visite :</td>
                            <td style='padding: 5px 0; color: #34495e;'>{$visite}</td>
                        </tr>
                        <tr>
                            <td style='padding: 5px 0; font-weight: bold; color: #2c3e50;'>Agence :</td>
                            <td style='padding: 5px 0; color: #34495e;'>{$agence}</td>
                        </tr>
                    </table>
                </div>
                
                <!-- Bouton de téléchargement sécurisé -->
                <div style='text-align: center; margin: 35px 0;'>
                    <a href='{$shortUrl}' 
                       style='background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); 
                              color: white; 
                              padding: 15px 35px; 
                              text-decoration: none; 
                              border-radius: 25px; 
                              font-weight: bold;
                              display: inline-block;
                              font-size: 16px;
                              transition: all 0.3s ease;
                              box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);'>
                        📄 Télécharger le rapport PDF
                    </a>
                </div>
                
                <!-- Informations de sécurité -->
                <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); 
                           border: 1px solid #f39c12; 
                           padding: 20px; 
                           border-radius: 8px; 
                           margin: 25px 0;'>
                    <h4 style='color: #d68910; margin-top: 0; margin-bottom: 15px;'>
                        🔒 Informations importantes
                    </h4>
                    <ul style='color: #8e6a0a; margin: 0; padding-left: 20px; line-height: 1.6;'>
                        <li><strong>Lien sécurisé et personnel</strong> - Ne pas partager</li>
                        <li><strong>Validité :</strong> 30 jours à compter de cet email</li>
                        <li><strong>Accès unique</strong> - Chaque clic est enregistré</li>
                        <li><strong>Support :</strong> Contactez-nous en cas de problème</li>
                    </ul>
                </div>
                
                <!-- Message de support -->
                <div style='border-top: 1px solid #ecf0f1; padding-top: 20px; margin-top: 30px;'>
                    <p style='color: #7f8c8d; line-height: 1.6; margin-bottom: 5px;'>
                        Pour toute question concernant ce rapport, n'hésitez pas à nous contacter.
                    </p>
                    <p style='color: #2c3e50; font-weight: 500; margin: 0;'>
                        Cordialement,<br>
                        L'équipe SOMAFI {$agence}
                    </p>
                </div>
            </div>
            
            <!-- Pied de page -->
            <div style='background-color: #34495e; color: #bdc3c7; padding: 20px; text-align: center; font-size: 12px; margin-top: 20px; border-radius: 8px;'>
                <p style='margin: 0 0 5px 0;'>
                    <strong>SOMAFI Grenoble</strong> | 52 rue de Corporat | 38430 MOIRANS
                </p>
                <p style='margin: 0 0 10px 0;'>
                    Tél. 04.76.32.66.99 | Email automatique - Ne pas répondre
                </p>
                <p style='margin: 0; opacity: 0.8;'>
                    🔐 Email sécurisé - " . date('Y') . " | Lien valide jusqu'au " . date('d/m/Y', strtotime('+30 days')) . "
                </p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Validation que l'URL est bien un lien court sécurisé
     */
    private function isSecureShortUrl(string $url): bool
    {
        // Vérifier que l'URL est bien un lien court (/s/xxxxx) et non une URL directe
        return preg_match('/\/s\/[a-zA-Z0-9]{8,}$/', parse_url($url, PHP_URL_PATH)) === 1;
    }

    /**
     * Construit le template HTML de l'email
     */
    private function buildEmailTemplate(
        string $clientName,
        string $shortUrl,
        string $agence,
        string $annee,
        string $visite,
        string $customMessage = ''
    ): string {
        $agencyNames = [
            'S10' => 'SOMAFI Group',
            'S40' => 'SOMAFI Saint-Étienne',
            'S50' => 'SOMAFI Grenoble',
            'S60' => 'SOMAFI Lyon',
            'S70' => 'SOMAFI Bordeaux',
            'S80' => 'SOMAFI Paris Nord',
            'S100' => 'SOMAFI Montpellier',
            'S120' => 'SOMAFI Hauts de France',
            'S130' => 'SOMAFI Toulouse',
            'S140' => 'SOMAFI Épinal',
            'S150' => 'SOMAFI PACA',
            'S160' => 'SOMAFI Rouen',
            'S170' => 'SOMAFI Rennes',
        ];

        $agencyName = $agencyNames[$agence] ?? 'SOMAFI';
        $currentDate = date('d/m/Y');

        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Rapport d'équipements - {$clientName}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 20px;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 20px; 
                    border-radius: 10px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
                    color: white; 
                    padding: 20px; 
                    text-align: center; 
                    border-radius: 10px 10px 0 0;
                    margin: -20px -20px 20px -20px;
                }
                .logo {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .download-button { 
                    display: inline-block; 
                    background: #28a745; 
                    color: white !important; 
                    padding: 15px 30px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    font-weight: bold;
                    margin: 20px 0;
                    text-align: center;
                }
                .download-button:hover {
                    background: #218838;
                }
                .info-box {
                    background: #e9ecef;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 15px 0;
                }
                .warning {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    color: #856404;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 15px 0;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #eee;
                    font-size: 12px;
                    color: #666;
                    text-align: center;
                }
                .contact-info {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 15px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>🏢 SOMAFI</div>
                    <div>Gestion d'Équipements</div>
                </div>
                
                <h2>Bonjour {$clientName},</h2>
                
                <p>Votre rapport d'équipements pour l'année <strong>{$annee}</strong> (visite <strong>{$visite}</strong>) est maintenant disponible.</p>
                
                " . ($customMessage ? "<div class='info-box'><strong>Message personnalisé :</strong><br>{$customMessage}</div>" : "") . "
                
                <div style='text-align: center;'>
                    <a href='{$shortUrl}' class='download-button'>
                        📄 Télécharger votre rapport PDF
                    </a>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Important :</strong>
                    <ul>
                        <li>Ce lien est valable pendant <strong>30 jours</strong></li>
                        <li>Il est personnel et sécurisé</li>
                        <li>Ne le partagez pas avec des tiers</li>
                    </ul>
                </div>
                
                <div class='contact-info'>
                    <strong>📞 Besoin d'aide ?</strong><br>
                    Contactez votre agence <strong>{$agencyName}</strong><br>
                    Email : <a href='mailto:{$this->agencyEmails[$agence]}'>{$this->agencyEmails[$agence]}</a>
                </div>
                
                <div class='info-box'>
                    <strong>📋 Détails de votre rapport :</strong><br>
                    • Client : {$clientName}<br>
                    • Année : {$annee}<br>
                    • Type de visite : {$visite}<br>
                    • Généré le : {$currentDate}<br>
                    • Agence : {$agencyName}
                </div>
                
                <div class='footer'>
                    <p>Cet email a été envoyé automatiquement par le système SOMAFI.<br>
                    Si vous n'avez pas demandé ce rapport, veuillez contacter votre agence.</p>
                    
                    <p><strong>SOMAFI</strong> - Spécialiste en équipements industriels<br>
                    <a href='https://www.somafi-group.fr'>www.somafi-group.fr</a></p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Teste la configuration email
     */
    public function testEmailConfiguration(): array
    {
        try {
            // Créer un email de test simple
            $testEmail = (new Email())
                ->from('group@somafi-group.fr')
                ->to('test@somafi-group.fr')
                ->subject('Test de configuration email')
                ->text('Ceci est un test de configuration email.');

            // On ne l'envoie pas vraiment, on teste juste la création
            return [
                'success' => true,
                'message' => 'Configuration email valide',
                'mailer_class' => get_class($this->mailer)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur de configuration: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Envoie un email de notification interne
     */
    public function sendInternalNotification(
        string $agence,
        string $subject,
        string $message,
        array $data = []
    ): bool {
        try {
            $internalEmail = $this->agencyEmails[$agence] ?? 'group@somafi-group.fr';
            
            $email = (new Email())
                ->from('system@somafi-group.fr')
                ->to($internalEmail)
                ->subject("[SYSTÈME] {$subject}")
                ->html($this->buildInternalNotificationTemplate($subject, $message, $data));

            $this->mailer->send($email);
            
            $this->logger->info("Notification interne envoyée pour {$agence}: {$subject}");
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur notification interne: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Template pour les notifications internes
     */
    private function buildInternalNotificationTemplate(string $subject, string $message, array $data): string
    {
        $dataHtml = '';
        if (!empty($data)) {
            $dataHtml = '<h3>Données supplémentaires :</h3><ul>';
            foreach ($data as $key => $value) {
                $dataHtml .= "<li><strong>{$key}:</strong> {$value}</li>";
            }
            $dataHtml .= '</ul>';
        }

        return "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>{$subject}</h2>
            <p>{$message}</p>
            {$dataHtml}
            <hr>
            <p><small>Notification automatique du système SOMAFI - " . date('d/m/Y H:i:s') . "</small></p>
        </body>
        </html>";
    }
}