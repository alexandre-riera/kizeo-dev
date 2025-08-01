<?php
// ===== ROUTES SUPPLÉMENTAIRES POUR SHORTLINKADMINCONTROLLER =====

namespace App\Controller\Admin;

use App\Entity\ShortLink;
use App\Repository\ShortLinkRepository;
use App\Service\ShortLinkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin/short-links', name: 'admin_short_links_')]
class ShortLinkAdminController extends AbstractController
{
    private ShortLinkRepository $repository;
    private ShortLinkService $shortLinkService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ShortLinkRepository $repository,
        ShortLinkService $shortLinkService,
        EntityManagerInterface $entityManager
    ) {
        $this->repository = $repository;
        $this->shortLinkService = $shortLinkService;
        $this->entityManager = $entityManager;
    }

    /**
     * Liste des liens courts avec statistiques - CORRIGÉE
     */
    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $agence = $request->query->get('agence');
        
        $qb = $this->repository->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC');
        
        if ($agence) {
            $qb->andWhere('s.agence = :agence')
               ->setParameter('agence', $agence);
        }
        
        $links = $qb->getQuery()->getResult();
        
        // Statistiques globales
        $stats = [
            'total_links' => count($links),
            'total_clicks' => array_sum(array_map(fn($link) => $link->getClickCount(), $links)),
            'expired_links' => count(array_filter($links, fn($link) => $link->isExpired())),
            'active_links' => count(array_filter($links, fn($link) => !$link->isExpired()))
        ];
        
        // CORRECTION : Créer la liste des agences manuellement au lieu d'utiliser |unique
        $agencies = [];
        foreach ($links as $link) {
            $agenceLink = $link->getAgence();
            if (!in_array($agenceLink, $agencies)) {
                $agencies[] = $agenceLink;
            }
        }
        sort($agencies); // Trier alphabétiquement
        
        return $this->render('admin/short_links/index.html.twig', [
            'links' => $links,
            'stats' => $stats,
            'agencies' => $agencies, // Passer la liste des agences au template
            'current_agence' => $agence
        ]);
    }

    /**
     * API pour les statistiques en temps réel
     */
    #[Route('/api/stats', name: 'api_stats')]
    public function apiStats(): JsonResponse
    {
        $links = $this->repository->findAll();
        
        $stats = [
            'total_links' => count($links),
            'total_clicks' => array_sum(array_map(fn($link) => $link->getClickCount(), $links)),
            'links_by_agency' => [],
            'clicks_by_agency' => [],
            'recent_activity' => []
        ];
        
        foreach ($links as $link) {
            $agence = $link->getAgence();
            
            if (!isset($stats['links_by_agency'][$agence])) {
                $stats['links_by_agency'][$agence] = 0;
                $stats['clicks_by_agency'][$agence] = 0;
            }
            
            $stats['links_by_agency'][$agence]++;
            $stats['clicks_by_agency'][$agence] += $link->getClickCount();
        }
        
        // Activité récente (derniers 10 accès)
        $recentLinks = $this->repository->createQueryBuilder('s')
            ->where('s.clickCount > 0')
            ->orderBy('s.lastAccessedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        foreach ($recentLinks as $link) {
            $stats['recent_activity'][] = [
                'agence' => $link->getAgence(),
                'client_id' => $link->getClientId(),
                'clicks' => $link->getClickCount(),
                'last_access' => $link->getLastAccessedAt()?->format('d/m/Y H:i')
            ];
        }
        
        return new JsonResponse($stats);
    }

    /**
     * Prolonger un lien
     */
    #[Route('/extend/{id}', name: 'extend', methods: ['POST'])]
    public function extendLink(Request $request, ShortLink $shortLink): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        try {
            $data = json_decode($request->getContent(), true);
            $days = $data['days'] ?? 30;
            
            $newExpiry = new \DateTime();
            $newExpiry->add(new \DateInterval("P{$days}D"));
            
            $shortLink->setExpiresAt($newExpiry);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => "Lien prolongé de {$days} jours",
                'new_expiry' => $newExpiry->format('d/m/Y H:i')
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la prolongation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un lien
     */
    #[Route('/delete/{id}', name: 'delete', methods: ['DELETE'])]
    public function deleteLink(ShortLink $shortLink): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        try {
            $this->entityManager->remove($shortLink);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Lien supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Nettoyage manuel des liens expirés
     */
    #[Route('/cleanup', name: 'cleanup', methods: ['POST'])]
    public function cleanup(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        try {
            $deletedCount = $this->shortLinkService->cleanupExpiredLinks();
            
            return new JsonResponse([
                'success' => true,
                'message' => "$deletedCount liens expirés supprimés",
                'deleted_count' => $deletedCount
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors du nettoyage : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exporter les statistiques
     */
    #[Route('/export', name: 'export')]
    public function exportStats(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $format = $request->query->get('format', 'csv');
        $agence = $request->query->get('agence');
        
        $qb = $this->repository->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC');
        
        if ($agence) {
            $qb->andWhere('s.agence = :agence')
               ->setParameter('agence', $agence);
        }
        
        $links = $qb->getQuery()->getResult();
        
        if ($format === 'csv') {
            return $this->exportToCsv($links, $agence);
        }
        
        return $this->exportToJson($links, $agence);
    }

    /**
     * Voir les détails d'un lien
     */
    #[Route('/details/{id}', name: 'details')]
    public function linkDetails(ShortLink $shortLink): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        return new JsonResponse([
            'id' => $shortLink->getId(),
            'short_code' => $shortLink->getShortCode(),
            'original_url' => $shortLink->getOriginalUrl(),
            'agence' => $shortLink->getAgence(),
            'client_id' => $shortLink->getClientId(),
            'annee' => $shortLink->getAnnee(),
            'visite' => $shortLink->getVisite(),
            'click_count' => $shortLink->getClickCount(),
            'created_at' => $shortLink->getCreatedAt()->format('d/m/Y H:i:s'),
            'expires_at' => $shortLink->getExpiresAt()?->format('d/m/Y H:i:s'),
            'last_accessed_at' => $shortLink->getLastAccessedAt()?->format('d/m/Y H:i:s'),
            'is_expired' => $shortLink->isExpired(),
            'full_short_url' => $this->shortLinkService->getShortUrl($shortLink->getShortCode())
        ]);
    }

    /**
     * Calcule les statistiques de base
     */
    private function calculateStats(array $links): array
    {
        $stats = [
            'total_links' => count($links),
            'total_clicks' => 0,
            'expired_links' => 0,
            'active_links' => 0
        ];
        
        foreach ($links as $link) {
            $stats['total_clicks'] += $link->getClickCount();
            
            if ($link->isExpired()) {
                $stats['expired_links']++;
            } else {
                $stats['active_links']++;
            }
        }
        
        return $stats;
    }

    /**
     * Calcule les statistiques détaillées
     */
    private function calculateDetailedStats(array $links): array
    {
        $stats = [
            'total_links' => count($links),
            'total_clicks' => 0,
            'links_by_agency' => [],
            'clicks_by_agency' => [],
            'recent_activity' => [],
            'expired_links' => 0,
            'active_links' => 0
        ];
        
        foreach ($links as $link) {
            $agence = $link->getAgence();
            $stats['total_clicks'] += $link->getClickCount();
            
            // Par agence
            if (!isset($stats['links_by_agency'][$agence])) {
                $stats['links_by_agency'][$agence] = 0;
                $stats['clicks_by_agency'][$agence] = 0;
            }
            
            $stats['links_by_agency'][$agence]++;
            $stats['clicks_by_agency'][$agence] += $link->getClickCount();
            
            // Statut
            if ($link->isExpired()) {
                $stats['expired_links']++;
            } else {
                $stats['active_links']++;
            }
            
            // Activité récente
            if ($link->getLastAccessedAt()) {
                $stats['recent_activity'][] = [
                    'agence' => $agence,
                    'client_id' => $link->getClientId(),
                    'clicks' => $link->getClickCount(),
                    'last_access' => $link->getLastAccessedAt()->format('Y-m-d H:i:s'),
                    'short_code' => $link->getShortCode()
                ];
            }
        }
        
        // Trier l'activité récente
        usort($stats['recent_activity'], function($a, $b) {
            return $b['last_access'] <=> $a['last_access'];
        });
        
        $stats['recent_activity'] = array_slice($stats['recent_activity'], 0, 10);
        
        return $stats;
    }

    /**
     * Export CSV
     */
    private function exportToCsv(array $links, ?string $agence): Response
    {
        $filename = 'short_links_' . ($agence ?: 'all') . '_' . date('Y-m-d') . '.csv';
        
        $output = fopen('php://memory', 'w');
        
        // Headers CSV
        fputcsv($output, [
            'Code Court',
            'Agence',
            'Client ID',
            'Année',
            'Visite',
            'Clics',
            'Créé le',
            'Expire le',
            'Statut',
            'URL Originale',
            'Dernier accès'
        ]);
        
        // Données
        foreach ($links as $link) {
            fputcsv($output, [
                $link->getShortCode(),
                $link->getAgence(),
                $link->getClientId(),
                $link->getAnnee(),
                $link->getVisite(),
                $link->getClickCount(),
                $link->getCreatedAt()->format('d/m/Y H:i'),
                $link->getExpiresAt()?->format('d/m/Y H:i') ?: 'Jamais',
                $link->isExpired() ? 'Expiré' : 'Actif',
                $link->getOriginalUrl(),
                $link->getLastAccessedAt()?->format('d/m/Y H:i') ?: 'Jamais'
            ]);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\""
        ]);
    }

    /**
     * Export JSON
     */
    private function exportToJson(array $links, ?string $agence): Response
    {
        $filename = 'short_links_' . ($agence ?: 'all') . '_' . date('Y-m-d') . '.json';
        
        $data = [
            'export_date' => date('Y-m-d H:i:s'),
            'agency_filter' => $agence,
            'total_links' => count($links),
            'links' => []
        ];
        
        foreach ($links as $link) {
            $data['links'][] = [
                'short_code' => $link->getShortCode(),
                'agence' => $link->getAgence(),
                'client_id' => $link->getClientId(),
                'annee' => $link->getAnnee(),
                'visite' => $link->getVisite(),
                'click_count' => $link->getClickCount(),
                'created_at' => $link->getCreatedAt()->format('Y-m-d H:i:s'),
                'expires_at' => $link->getExpiresAt()?->format('Y-m-d H:i:s'),
                'last_accessed_at' => $link->getLastAccessedAt()?->format('Y-m-d H:i:s'),
                'is_expired' => $link->isExpired(),
                'original_url' => $link->getOriginalUrl(),
                'full_short_url' => $this->shortLinkService->getShortUrl($link->getShortCode())
            ];
        }
        
        return new Response(json_encode($data, JSON_PRETTY_PRINT), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\""
        ]);
    }
}