<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion du cache pour les données Kizeo
 */
class KizeoCacheService
{
    private const CACHE_PREFIX = 'kizeo_equipments_';
    private const DEFAULT_TTL = 900; // 15 minutes
    
    public function __construct(
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Récupère les équipements Kizeo depuis le cache ou l'API
     */
    public function getKizeoEquipments(string $entityName, int $listId, callable $dataProvider): array
    {
        $cacheKey = $this->generateCacheKey($entityName);
        
        try {
            return $this->cache->get($cacheKey, function(ItemInterface $item) use ($dataProvider) {
                $item->expiresAfter(self::DEFAULT_TTL);
                
                $this->logger->info('Cache miss - Récupération depuis API Kizeo', [
                    'cache_key' => $item->getKey()
                ]);
                
                return $dataProvider();
            });
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du cache Kizeo', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            
            // En cas d'erreur de cache, appeler directement le provider
            return $dataProvider();
        }
    }
    
    /**
     * Met à jour le cache avec de nouvelles données
     */
    public function updateKizeoEquipments(string $entityName, array $equipments): bool
    {
        $cacheKey = $this->generateCacheKey($entityName);
        
        try {
            $this->cache->delete($cacheKey);
            
            $this->cache->get($cacheKey, function(ItemInterface $item) use ($equipments) {
                $item->expiresAfter(self::DEFAULT_TTL);
                return $equipments;
            });
            
            $this->logger->info('Cache Kizeo mis à jour', [
                'cache_key' => $cacheKey,
                'equipment_count' => count($equipments)
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour du cache Kizeo', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Invalide le cache pour une entité spécifique
     */
    public function invalidateEntityCache(string $entityName): bool
    {
        $cacheKey = $this->generateCacheKey($entityName);
        
        try {
            $deleted = $this->cache->delete($cacheKey);
            
            if ($deleted) {
                $this->logger->info('Cache invalidé', ['cache_key' => $cacheKey]);
            }
            
            return $deleted;
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'invalidation du cache', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Invalide tout le cache Kizeo
     */
    public function invalidateAllKizeoCache(): array
    {
        $entities = ['s10', 's40', 's50', 's60', 's70', 's80', 's100', 's120', 's130', 's140', 's150', 's160', 's170'];
        $results = [];
        
        foreach ($entities as $entity) {
            $results[$entity] = $this->invalidateEntityCache($entity);
        }
        
        $successCount = count(array_filter($results));
        
        $this->logger->info('Invalidation globale du cache Kizeo', [
            'total_entities' => count($entities),
            'success_count' => $successCount
        ]);
        
        return $results;
    }
    
    /**
     * Vérifie si une entité est en cache
     */
    public function hasEntityInCache(string $entityName): bool
    {
        $cacheKey = $this->generateCacheKey($entityName);
        
        try {
            // Tentative de récupération sans provider pour tester l'existence
            $this->cache->get($cacheKey, function() {
                throw new \RuntimeException('Cache miss');
            });
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Récupère les statistiques du cache
     */
    public function getCacheStats(): array
    {
        $entities = ['s10', 's40', 's50', 's60', 's70', 's80', 's100', 's120', 's130', 's140', 's150', 's160', 's170'];
        $stats = [
            'total_entities' => count($entities),
            'cached_entities' => 0,
            'cache_keys' => []
        ];
        
        foreach ($entities as $entity) {
            $cacheKey = $this->generateCacheKey($entity);
            $inCache = $this->hasEntityInCache($entity);
            
            $stats['cache_keys'][$entity] = [
                'key' => $cacheKey,
                'cached' => $inCache
            ];
            
            if ($inCache) {
                $stats['cached_entities']++;
            }
        }
        
        $stats['cache_hit_ratio'] = $stats['total_entities'] > 0 
            ? round(($stats['cached_entities'] / $stats['total_entities']) * 100, 2) 
            : 0;
            
        return $stats;
    }
    
    /**
     * Génère la clé de cache pour une entité
     */
    private function generateCacheKey(string $entityName): string
    {
        return self::CACHE_PREFIX . strtolower($entityName);
    }
    
    /**
     * Récupère les données avec retry en cas d'échec
     */
    public function getWithRetry(string $entityName, int $listId, callable $dataProvider, int $maxRetries = 3): array
    {
        $attempts = 0;
        
        while ($attempts < $maxRetries) {
            try {
                return $this->getKizeoEquipments($entityName, $listId, $dataProvider);
                
            } catch (\Exception $e) {
                $attempts++;
                
                $this->logger->warning('Tentative de récupération échouée', [
                    'entity' => $entityName,
                    'attempt' => $attempts,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage()
                ]);
                
                if ($attempts >= $maxRetries) {
                    throw $e;
                }
                
                // Attente exponentielle : 1s, 2s, 4s...
                sleep(pow(2, $attempts - 1));
            }
        }
        
        return [];
    }
}

// Configuration du service dans services.yaml :
/*
services:
    App\Service\KizeoCacheService:
        arguments:
            $cache: '@cache.app'
            $logger: '@monolog.logger.kizeo_cache'
        tags:
            - { name: 'monolog.logger', channel: 'kizeo_cache' }
*/