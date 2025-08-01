<?php

// ===== CONTRÔLEUR ADMIN POUR GÉRER LES LIENS COURTS =====

namespace App\Controller\Admin;

use App\Entity\ShortLink;
use App\Repository\ShortLinkRepository;
use App\Service\ShortLinkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/short-links', name: 'admin_short_links_')]
class ShortLinkAdminController extends AbstractController
{
    private ShortLinkRepository $repository;
    private ShortLinkService $shortLinkService;

    public function __construct(
        ShortLinkRepository $repository,
        ShortLinkService $shortLinkService
    ) {
        $this->repository = $repository;
        $this->shortLinkService = $shortLinkService;
    }

    /**
     * Liste des liens courts avec statistiques
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
        
        return $this->render('admin/short_links/index.html.twig', [
            'links' => $links,
            'stats' => $stats,
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
            
            if ($link->getLastAccessedAt()) {
                $stats['recent_activity'][] = [
                    'agence' => $agence,
                    'client_id' => $link->getClientId(),
                    'clicks' => $link->getClickCount(),
                    'last_access' => $link->getLastAccessedAt()->format('Y-m-d H:i:s')
                ];
            }
        }
        
        // Trier l'activité récente
        usort($stats['recent_activity'], function($a, $b) {
            return $b['last_access'] <=> $a['last_access'];
        });
        
        $stats['recent_activity'] = array_slice($stats['recent_activity'], 0, 10);
        
        return new JsonResponse($stats);
    }

    /**
     * Nettoyage manuel des liens expirés
     */
    #[Route('/cleanup', name: 'cleanup', methods: ['POST'])]
    public function cleanup(): JsonResponse
    {
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
}