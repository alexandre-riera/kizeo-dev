<?php

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

    public function __construct(MaintenanceCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test de connexion Redis o2switch avec SncRedisBundle');

        // Test simple
        try {
            $connectionTest = $this->cacheService->testConnection();
            
            if ($connectionTest['connected']) {
                $io->success('✅ Redis fonctionne !');
                $io->table(['Métrique', 'Valeur'], [
                    ['Connecté', 'Oui'],
                    ['Version', $connectionTest['redis_version'] ?? 'N/A']
                ]);
            } else {
                $io->error('❌ Redis ne fonctionne pas : ' . ($connectionTest['error'] ?? 'Erreur inconnue'));
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $io->error("❌ Erreur : " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}