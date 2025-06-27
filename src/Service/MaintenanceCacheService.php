<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Redis;

class MaintenanceCacheService
{
    private $redis;  // Interface SncRedis, pas Redis natif
    private LoggerInterface $logger;
    
    // Durées de cache en secondes
    private const CACHE_TTL_SUBMISSIONS_LIST = 900; // 15 minutes
    private const CACHE_TTL_SUBMISSION_RAW = 2592000; // 30 jours
    private const CACHE_TTL_SUBMISSION_PROCESSED = 604800; // 7 jours
    
    public function __construct($redis, LoggerInterface $logger)  // Interface générique
    {
        $this->redis = $redis;
        $this->logger = $logger;
        
        // Configuration spécifique pour SncRedis o2switch
        try {
            // Test de connexion avec SncRedis
            $this->redis->ping();
            
            $this->logger->info('Service Redis SncRedis initialisé', [
                'client_type' => get_class($this->redis),
                'connection_test' => 'OK'
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur initialisation Redis SncRedis', [
                'error' => $e->getMessage(),
                'client_type' => get_class($this->redis)
            ]);
            throw $e;
        }
    }
    
    /**
     * Récupère la liste des soumissions en cache pour une agence
     */
    public function getSubmissionsList(string $agencyCode, string $formId): ?array
    {
        try {
            $cacheKey = $this->getSubmissionsListKey($agencyCode, $formId);
            $cached = $this->redis->get($cacheKey);
            
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération liste soumissions cache', [
                'agency' => $agencyCode,
                'form_id' => $formId,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
    /**
     * Sauvegarde la liste des soumissions en cache
     */
    public function saveSubmissionsList(string $agencyCode, string $formId, array $submissionIds): bool
    {
        try {
            $cacheKey = $this->getSubmissionsListKey($agencyCode, $formId);
            $this->redis->setex($cacheKey, self::CACHE_TTL_SUBMISSIONS_LIST, json_encode($submissionIds));
            
            $this->logger->info('Liste soumissions sauvegardée en cache', [
                'agency' => $agencyCode,
                'form_id' => $formId,
                'count' => count($submissionIds)
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur sauvegarde liste soumissions cache', [
                'agency' => $agencyCode,
                'form_id' => $formId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Récupère une soumission brute depuis le cache
     */
    public function getRawSubmission(string $agencyCode, string $submissionId): ?array
    {
        try {
            $cacheKey = $this->getRawSubmissionKey($agencyCode, $submissionId);
            $cached = $this->redis->get($cacheKey);
            
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération soumission brute cache', [
                'agency' => $agencyCode,
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
    /**
     * Sauvegarde une soumission brute en cache
     */
    public function saveRawSubmission(string $agencyCode, string $submissionId, array $submission): bool
    {
        try {
            $cacheKey = $this->getRawSubmissionKey($agencyCode, $submissionId);
            $this->redis->setex($cacheKey, self::CACHE_TTL_SUBMISSION_RAW, json_encode($submission));
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur sauvegarde soumission brute cache', [
                'agency' => $agencyCode,
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Récupère une soumission traitée depuis le cache
     */
    public function getProcessedSubmission(string $agencyCode, string $submissionId): ?array
    {
        try {
            $cacheKey = $this->getProcessedSubmissionKey($agencyCode, $submissionId);
            $cached = $this->redis->get($cacheKey);
            
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération soumission traitée cache', [
                'agency' => $agencyCode,
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
    /**
     * Sauvegarde une soumission traitée en cache
     */
    public function saveProcessedSubmission(string $agencyCode, string $submissionId, array $processedData): bool
    {
        try {
            $cacheKey = $this->getProcessedSubmissionKey($agencyCode, $submissionId);
            $this->redis->setex($cacheKey, self::CACHE_TTL_SUBMISSION_PROCESSED, json_encode($processedData));
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur sauvegarde soumission traitée cache', [
                'agency' => $agencyCode,
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Récupère plusieurs soumissions en une fois (pipeline)
     */
    public function getBulkSubmissions(string $agencyCode, array $submissionIds, bool $processed = false): array
    {
        $results = [];
        
        try {
            $pipe = $this->redis->pipeline();
            
            foreach ($submissionIds as $submissionId) {
                $cacheKey = $processed 
                    ? $this->getProcessedSubmissionKey($agencyCode, $submissionId)
                    : $this->getRawSubmissionKey($agencyCode, $submissionId);
                $pipe->get($cacheKey);
            }
            
            $responses = $pipe->exec();
            
            foreach ($responses as $index => $response) {
                if ($response) {
                    $submissionId = $submissionIds[$index];
                    $results[$submissionId] = json_decode($response, true);
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération bulk soumissions', [
                'agency' => $agencyCode,
                'count' => count($submissionIds),
                'processed' => $processed,
                'error' => $e->getMessage()
            ]);
        }
        
        return $results;
    }
    
    /**
     * Vide le cache pour une agence spécifique
     */
    public function clearAgencyCache(string $agencyCode, ?string $formId = null): int
    {
        $deletedCount = 0;
        
        try {
            $patterns = [
                "submission_raw_{$agencyCode}_*",
                "submission_processed_{$agencyCode}_*"
            ];
            
            if ($formId) {
                $patterns[] = $this->getSubmissionsListKey($agencyCode, $formId);
            }
            
            foreach ($patterns as $pattern) {
                $keys = $this->redis->keys($pattern);
                if (!empty($keys)) {
                    $deleted = $this->redis->del($keys);
                    $deletedCount += is_array($deleted) ? count($deleted) : $deleted;
                }
            }
            
            $this->logger->info('Cache agence vidé', [
                'agency' => $agencyCode,
                'form_id' => $formId,
                'deleted_keys' => $deletedCount
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur vidage cache agence', [
                'agency' => $agencyCode,
                'form_id' => $formId,
                'error' => $e->getMessage()
            ]);
        }
        
        return $deletedCount;
    }
    
    /**
     * Obtient des statistiques sur le cache
     */
    public function getCacheStats(string $agencyCode): array
    {
        try {
            $patterns = [
                'raw' => "submission_raw_{$agencyCode}_*",
                'processed' => "submission_processed_{$agencyCode}_*"
            ];
            
            $stats = [
                'agency' => $agencyCode,
                'total_keys' => 0,
                'raw_submissions' => 0,
                'processed_submissions' => 0,
                'memory_usage' => 0
            ];
            
            foreach ($patterns as $type => $pattern) {
                $keys = $this->redis->keys($pattern);
                $count = count($keys);
                $stats[$type . '_submissions'] = $count;
                $stats['total_keys'] += $count;
                
                // Estimation de l'usage mémoire (sample de quelques clés)
                if ($count > 0) {
                    $sampleKeys = array_slice($keys, 0, min(10, $count));
                    $sampleSize = 0;
                    foreach ($sampleKeys as $key) {
                        $sampleSize += strlen($this->redis->get($key));
                    }
                    $stats['memory_usage'] += ($sampleSize / count($sampleKeys)) * $count;
                }
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération stats cache', [
                'agency' => $agencyCode,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Teste la connexion Redis via socket
     */
    public function testConnection(): array
    {
        try {
            $info = $this->redis->info();
            
            return [
                'connected' => true,
                'redis_version' => $info['redis_version'] ?? 'unknown',
                'connection_type' => 'unix_socket',
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 'unknown',
                'uptime' => $info['uptime_in_seconds'] ?? 'unknown'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur test connexion Redis', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Nettoie toutes les connexions Redis et reconnecte pour o2switch
     */
    public function reconnect(): bool
    {
        try {
            $this->redis->close();
            
            // Reconnecter via le socket et mot de passe o2switch
            $socketPath = $_ENV['REDIS_SOCKET'] ?? '/home2/divi4480/.cpanel/redis/redis.sock';
            $password = $_ENV['REDIS_PASSWORD'] ?? '';
            
            $this->redis->connect($socketPath);
            
            // Authentification obligatoire sur o2switch
            if ($password) {
                $this->redis->auth($password);
            }
            
            // Resélectionner la base de données
            $db = $_ENV['REDIS_DB'] ?? 0;
            $this->redis->select($db);
            
            $this->logger->info('Reconnexion Redis o2switch réussie', [
                'socket' => $socketPath,
                'database' => $db,
                'authenticated' => !empty($password)
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur reconnexion Redis o2switch', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    // Méthodes privées pour générer les clés de cache
    
    private function getSubmissionsListKey(string $agencyCode, string $formId): string
    {
        return "agency_submissions_{$agencyCode}_{$formId}";
    }
    
    private function getRawSubmissionKey(string $agencyCode, string $submissionId): string
    {
        return "submission_raw_{$agencyCode}_{$submissionId}";
    }
    
    private function getProcessedSubmissionKey(string $agencyCode, string $submissionId): string
    {
        return "submission_processed_{$agencyCode}_{$submissionId}";
    }
}