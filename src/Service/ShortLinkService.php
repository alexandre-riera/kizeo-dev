<?php
// ===== 4. SERVICE DE GESTION DES LIENS COURTS =====

namespace App\Service;

use App\Entity\ShortLink;
use App\Repository\ShortLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ShortLinkService
{
    private EntityManagerInterface $entityManager;
    private ShortLinkRepository $repository;
    private LoggerInterface $logger;
    private string $baseUrl;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        string $baseUrl = 'https://votre-domaine.com'
    ) {
        $this->entityManager = $entityManager;
        $this->repository = $entityManager->getRepository(ShortLink::class);
        $this->logger = $logger;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Crée un lien court pour un PDF client
     */
    public function createShortLink(
        string $originalUrl,
        string $agence,
        string $clientId,
        string $annee,
        string $visite,
        ?\DateTimeInterface $expiresAt = null
    ): ShortLink {
        // Vérifier si un lien existe déjà pour ces paramètres
        $existing = $this->repository->findOneBy([
            'agence' => $agence,
            'clientId' => $clientId,
            'annee' => $annee,
            'visite' => $visite
        ]);

        if ($existing && !$existing->isExpired()) {
            $this->logger->info("Lien court existant réutilisé: {$existing->getShortCode()}");
            return $existing;
        }

        // Générer un code court unique
        $shortCode = $this->generateUniqueShortCode();
        
        $shortLink = new ShortLink();
        $shortLink->setShortCode($shortCode)
                  ->setOriginalUrl($originalUrl)
                  ->setAgence($agence)
                  ->setClientId($clientId)
                  ->setAnnee($annee)
                  ->setVisite($visite)
                  ->setCreatedAt(new \DateTime());

        if ($expiresAt) {
            $shortLink->setExpiresAt($expiresAt);
        }

        $this->entityManager->persist($shortLink);
        $this->entityManager->flush();

        $this->logger->info("Nouveau lien court créé: {$shortCode} pour {$agence}/{$clientId}");
        
        return $shortLink;
    }

    /**
     * Récupère un lien par son code court
     */
    public function getByShortCode(string $shortCode): ?ShortLink
    {
        return $this->repository->findActiveByShortCode($shortCode);
    }

    /**
     * Enregistre un accès au lien court
     */
    public function recordAccess(ShortLink $shortLink): void
    {
        $shortLink->incrementClickCount();
        $this->entityManager->flush();
        
        $this->logger->info("Accès enregistré pour le lien: {$shortLink->getShortCode()}");
    }

    /**
     * Génère l'URL complète du lien court
     */
    public function getShortUrl(string $shortCode): string
    {
        return $this->baseUrl . '/s/' . $shortCode;
    }

    /**
     * Nettoie les liens expirés
     */
    public function cleanupExpiredLinks(): int
    {
        $count = $this->repository->cleanupExpiredLinks();
        $this->logger->info("Nettoyage terminé: {$count} liens expirés supprimés");
        return $count;
    }

    /**
     * Génère un code court unique
     */
    private function generateUniqueShortCode(int $length = 8): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 100;
        
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $shortCode = '';
            for ($i = 0; $i < $length; $i++) {
                $shortCode .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            // Vérifier l'unicité
            if (!$this->repository->findByShortCode($shortCode)) {
                return $shortCode;
            }
        }
        
        throw new \RuntimeException("Impossible de générer un code court unique après {$maxAttempts} tentatives");
    }
}