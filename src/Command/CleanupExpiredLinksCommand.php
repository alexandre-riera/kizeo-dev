<?php

// ===== COMMANDE POUR NETTOYER LES LIENS EXPIRÉS =====

namespace App\Command;

use App\Service\ShortLinkService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-expired-links',
    description: 'Nettoie les liens courts expirés'
)]
class CleanupExpiredLinksCommand extends Command
{
    private ShortLinkService $shortLinkService;

    public function __construct(ShortLinkService $shortLinkService)
    {
        $this->shortLinkService = $shortLinkService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Nettoyage des liens courts expirés');
        
        try {
            $deletedCount = $this->shortLinkService->cleanupExpiredLinks();
            
            if ($deletedCount > 0) {
                $io->success("$deletedCount liens expirés ont été supprimés.");
            } else {
                $io->info('Aucun lien expiré trouvé.');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Erreur lors du nettoyage : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}