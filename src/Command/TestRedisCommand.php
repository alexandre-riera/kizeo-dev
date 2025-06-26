<?php

/**
 * Script de test pour Redis o2switch
 * À placer dans src/Command/TestRedisCommand.php
 */

namespace App\Command;

use App\Service\MaintenanceCacheService;
use Redis;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-redis',
    description: 'Test de connexion Redis o2switch'
)]
class TestRedisCommand extends Command
{
    private MaintenanceCacheService $cacheService;

    public function __construct(MaintenanceCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test de connexion Redis o2switch');

        // Test 1 : Configuration
        $io->section('1. Configuration Redis');
        $socketPath = $_ENV['REDIS_SOCKET'] ?? 'NON_DEFINI';
        $password = $_ENV['REDIS_PASSWORD'] ?? 'NON_DEFINI';
        $database = $_ENV['REDIS_DB'] ?? 'NON_DEFINI';

        $io->table(['Paramètre', 'Valeur'], [
            ['Socket', $socketPath],
            ['Mot de passe', substr($password, 0, 8) . '...'],
            ['Base de données', $database]
        ]);

        // Test 2 : Connexion directe
        $io->section('2. Test de connexion directe');
        try {
            $redis = new Redis();
            $redis->connect($socketPath);
            
            if ($password && $password !== 'NON_DEFINI') {
                $redis->auth($password);
            }
            
            $redis->select((int)$database);
            
            $ping = $redis->ping();
            $io->success("Connexion directe réussie : " . $ping);
            
            // Info Redis
            $info = $redis->info();
            $io->table(['Information', 'Valeur'], [
                ['Version Redis', $info['redis_version'] ?? 'N/A'],
                ['Mémoire utilisée', $info['used_memory_human'] ?? 'N/A'],
                ['Clients connectés', $info['connected_clients'] ?? 'N/A'],
                ['Uptime', ($info['uptime_in_seconds'] ?? 0) . ' secondes']
            ]);
            
            $redis->close();
            
        } catch (\Exception $e) {
            $io->error("Erreur connexion directe : " . $e->getMessage());
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
                    ['Version', $connectionTest['redis_version']],
                    ['Type connexion', $connectionTest['connection_type']],
                    ['Mémoire utilisée', $connectionTest['used_memory']],
                    ['Clients connectés', $connectionTest['connected_clients']]
                ]);
            } else {
                $io->error('Service de cache non fonctionnel : ' . $connectionTest['error']);
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $io->error("Erreur service de cache : " . $e->getMessage());
            return Command::FAILURE;
        }

        // Test 4 : Cache basique
        $io->section('4. Test cache basique');
        try {
            $testData = ['test' => 'valeur', 'timestamp' => time()];
            $saved = $this->cacheService->saveRawSubmission('TEST', 'test_submission', $testData);
            
            if ($saved) {
                $io->success('Sauvegarde test réussie');
                
                $retrieved = $this->cacheService->getRawSubmission('TEST', 'test_submission');
                if ($retrieved && $retrieved['test'] === 'valeur') {
                    $io->success('Récupération test réussie');
                } else {
                    $io->error('Erreur récupération test');
                    return Command::FAILURE;
                }
            } else {
                $io->error('Erreur sauvegarde test');
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $io->error("Erreur test cache : " . $e->getMessage());
            return Command::FAILURE;
        }

        // Test 5 : Statistiques
        $io->section('5. Statistiques cache');
        try {
            $stats = $this->cacheService->getCacheStats('TEST');
            $io->table(['Statistique', 'Valeur'], [
                ['Agence', $stats['agency']],
                ['Clés totales', $stats['total_keys']],
                ['Soumissions brutes', $stats['raw_submissions']],
                ['Soumissions traitées', $stats['processed_submissions']],
                ['Usage mémoire estimé', round($stats['memory_usage'] / 1024, 2) . ' KB']
            ]);
            
        } catch (\Exception $e) {
            $io->warning("Erreur statistiques : " . $e->getMessage());
        }

        // Nettoyage
        $this->cacheService->clearAgencyCache('TEST');
        
        $io->success('Tous les tests Redis o2switch sont passés avec succès !');
        
        return Command::SUCCESS;
    }
}

/*
UTILISATION :

1. Placez ce fichier dans src/Command/TestRedisCommand.php

2. Exécutez le test :
   php bin/console app:test-redis

3. Si erreurs, vérifiez :
   - Le chemin du socket dans cPanel
   - Le mot de passe (il change après redémarrage Redis)
   - Les permissions du fichier socket
   - La configuration dans .env

4. Pour debug approfondi, activez les logs :
   tail -f var/log/maintenance_cache.log
*/