<?php

/**
 * Script de test pour Redis o2switch avec SncRedisBundle
 * À placer dans src/Command/TestRedisCommand.php
 */

namespace App\Command;

use App\Service\MaintenanceCacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-redis',
    description: 'Test de connexion Redis o2switch avec SncRedisBundle'
)]
class TestRedisCommand extends Command
{
    private MaintenanceCacheService $cacheService;
    private $redisClient;

    public function __construct(
        MaintenanceCacheService $cacheService,
        $redisClient  // Interface SncRedis
    ) {
        $this->cacheService = $cacheService;
        $this->redisClient = $redisClient;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test de connexion Redis o2switch avec SncRedisBundle');

        // Test 1 : Configuration
        $io->section('1. Configuration Redis SncRedis');
        $redisUrl = $_ENV['REDIS_URL'] ?? 'NON_DEFINI';
        $cacheUrl = $_ENV['REDIS_CACHE_URL'] ?? 'NON_DEFINI';

        $io->table(['Paramètre', 'Valeur'], [
            ['Client type', get_class($this->redisClient)],
            ['Redis URL', $this->maskPassword($redisUrl)],
            ['Cache URL', $this->maskPassword($cacheUrl)]
        ]);

        // Test 2 : Connexion SncRedis
        $io->section('2. Test de connexion SncRedis');
        try {
            $ping = $this->redisClient->ping();
            $io->success("Connexion SncRedis réussie : " . $ping);
            
            // Informations client
            try {
                $info = $this->redisClient->info();
                $io->table(['Information', 'Valeur'], [
                    ['Version Redis', $info['redis_version'] ?? 'N/A'],
                    ['Mémoire utilisée', $info['used_memory_human'] ?? 'N/A'],
                    ['Clients connectés', $info['connected_clients'] ?? 'N/A'],
                    ['Uptime', ($info['uptime_in_seconds'] ?? 0) . ' secondes']
                ]);
            } catch (\Exception $e) {
                $io->warning('Info Redis non disponible : ' . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            $io->error("Erreur connexion SncRedis : " . $e->getMessage());
            return Command::FAILURE;
        }

        // Test 3 : Service de cache
        $io->section('3. Test du service de cache');
        try {
            $connectionTest = $this->cacheService->testConnection();
            
            if ($connectionTest['connected']) {
                $io->success('Service de cache fonctionnel');
                $io->table(['Métrique', 'Valeur'], [
                    ['Connecté', $connectionTest['connected'] ? 'Oui' : 'Non'],
                    ['Version', $connectionTest['redis_version'] ?? 'N/A'],
                    ['Type connexion', $connectionTest['connection_type'] ?? 'N/A'],
                    ['Mémoire utilisée', $connectionTest['used_memory'] ?? 'N/A']
                ]);
            } else {
                $io->error('Service de cache non fonctionnel : ' . ($connectionTest['error'] ?? 'Erreur inconnue'));
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $io->error("Erreur service de cache : " . $e->getMessage());
            return Command::FAILURE;
        }

        // Test 4 : Opérations basiques
        $io->section('4. Test opérations basiques');
        try {
            // Test SET/GET
            $testKey = 'test_snc_redis_' . time();
            $testValue = json_encode(['test' => 'valeur', 'timestamp' => time()]);
            
            $this->redisClient->set($testKey, $testValue);
            $io->success('SET réussi');
            
            $retrieved = $this->redisClient->get($testKey);
            if ($retrieved === $testValue) {
                $io->success('GET réussi');
            } else {
                $io->error('Erreur GET : valeur différente');
                return Command::FAILURE;
            }
            
            // Test EXPIRE
            $this->redisClient->expire($testKey, 60);
            $ttl = $this->redisClient->ttl($testKey);
            if ($ttl > 0 && $ttl <= 60) {
                $io->success("EXPIRE réussi : TTL = {$ttl}s");
            }
            
            // Nettoyage
            $this->redisClient->del($testKey);
            $io->success('DEL réussi');
            
        } catch (\Exception $e) {
            $io->error("Erreur opérations basiques : " . $e->getMessage());
            return Command::FAILURE;
        }

        // Test 5 : Cache service
        $io->section('5. Test cache service avancé');
        try {
            $testData = ['test' => 'valeur_service', 'timestamp' => time()];
            
            // Test via le service
            $saved = $this->cacheService->saveRawSubmission('TEST', 'test_submission', $testData);
            if ($saved) {
                $io->success('Sauvegarde service réussie');
                
                $retrieved = $this->cacheService->getRawSubmission('TEST', 'test_submission');
                if ($retrieved && $retrieved['test'] === 'valeur_service') {
                    $io->success('Récupération service réussie');
                } else {
                    $io->error('Erreur récupération service');
                    return Command::FAILURE;
                }
            } else {
                $io->error('Erreur sauvegarde service');
                return Command::FAILURE;
            }
            
            // Nettoyage
            $this->cacheService->clearAgencyCache('TEST');
            
        } catch (\Exception $e) {
            $io->error("Erreur test service : " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('✅ Tous les tests Redis SncRedis o2switch sont passés !');
        
        $io->note([
            'Configuration validée avec SncRedisBundle',
            'Utilisez maintenant l\'API avec cache activé :',
            'GET /api/maintenance/process-fixed/S40?use_cache=true'
        ]);
        
        return Command::SUCCESS;
    }
    
    private function maskPassword(string $url): string
    {
        return preg_replace('/(:)([^@]+)(@)/', '$1***$3', $url);
    }
}