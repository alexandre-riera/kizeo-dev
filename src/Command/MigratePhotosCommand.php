<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\FormRepository;
use App\Service\ImageStorageService;

#[AsCommand(
    name: 'app:migrate-photos',
    description: 'Migre les photos depuis l\'API Kizeo vers le stockage local'
)]
class MigratePhotosCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormRepository $formRepository,
        private ImageStorageService $imageStorageService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S140, S50, etc.)')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Taille des lots', 50)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer le re-téléchargement des photos existantes')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Simulation sans modification')
            ->addOption('clean-orphans', 'c', InputOption::VALUE_NONE, 'Nettoyer les photos orphelines après migration')
            ->addOption('verify', 'v', InputOption::VALUE_NONE, 'Vérifier l\'intégrité des images après téléchargement')
            ->setHelp('
Cette commande migre les photos des équipements depuis l\'API Kizeo Forms vers un stockage local.

Exemples d\'utilisation:
  php bin/console app:migrate-photos S140
  php bin/console app:migrate-photos S140 --batch-size=25 --verify
  php bin/console app:migrate-photos S140 --dry-run
  php bin/console app:migrate-photos S140 --force --clean-orphans
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agency = $input->getArgument('agency');
        $batchSize = $input->getOption('batch-size');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $cleanOrphans = $input->getOption('clean-orphans');
        $verify = $input->getOption('verify');

        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];

        if (!in_array($agency, $validAgencies)) {
            $io->error("Code agence invalide. Agences valides: " . implode(', ', $validAgencies));
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('Mode simulation activé - aucune modification ne sera effectuée');
        }

        $io->title("Migration des photos pour l'agence {$agency}");

        // Étape 1: Analyse initiale
        $io->section('📊 Analyse initiale');
        $report = $this->formRepository->getPhotoMigrationReport($agency);
        
        $io->definitionList(
            ['Équipements total' => $report['total_equipments']],
            ['Avec photos locales' => $report['equipments_with_local_photos']],
            ['Sans photos locales' => $report['equipments_without_local_photos']],
            ['Pourcentage migré' => $report['migration_percentage'] . '%'],
            ['Stockage utilisé' => $report['storage_used']]
        );

        if (!$force && $report['equipments_without_local_photos'] === 0) {
            $io->success('Toutes les photos sont déjà migrées !');
            return Command::SUCCESS;
        }

        // Étape 2: Migration
        if (!$dryRun) {
            $io->section('🔄 Migration des photos');
            
            $migrationResults = $this->performMigration($agency, $batchSize, $force, $verify, $io);
            
            $io->definitionList(
                ['Équipements traités' => $migrationResults['processed']],
                ['Photos migrées' => $migrationResults['migrated']],
                ['Ignorés' => $migrationResults['skipped']],
                ['Erreurs' => $migrationResults['errors']],
                ['Lots complétés' => $migrationResults['batches_completed']]
            );

            if ($migrationResults['errors'] > 0) {
                $io->warning("Des erreurs sont survenues pendant la migration");
            }
        }

        // Étape 3: Nettoyage des orphelins
        if ($cleanOrphans && !$dryRun) {
            $io->section('🧹 Nettoyage des photos orphelines');
            
            $cleanResults = $this->formRepository->cleanOrphanedPhotos($agency);
            
            $io->definitionList(
                ['Photos vérifiées' => $cleanResults['checked']],
                ['Photos supprimées' => $cleanResults['deleted']],
                ['Espace libéré' => $this->formatBytes($cleanResults['size_freed'])],
                ['Erreurs' => $cleanResults['errors']]
            );
        }

        // Étape 4: Rapport final
        $io->section('📈 Rapport final');
        $finalReport = $this->formRepository->getPhotoMigrationReport($agency);
        
        $io->definitionList(
            ['Pourcentage migré' => $finalReport['migration_percentage'] . '%'],
            ['Total photos locales' => $finalReport['total_local_photos']],
            ['Stockage utilisé' => $finalReport['storage_used']],
            ['Moyenne photos/équipement' => $finalReport['average_photos_per_equipment']]
        );

        if ($finalReport['migration_percentage'] >= 90) {
            $io->success('Migration terminée avec succès !');
        } elseif ($finalReport['migration_percentage'] >= 50) {
            $io->warning('Migration partiellement réussie');
        } else {
            $io->error('Migration échouée - vérifiez les logs');
            return Command::FAILURE;
        }

        // Conseils post-migration
        $this->displayPostMigrationTips($io, $agency, $finalReport);

        return Command::SUCCESS;
    }

    private function performMigration(string $agency, int $batchSize, bool $force, bool $verify, SymfonyStyle $io): array
    {
        $results = $this->formRepository->migrateAllEquipmentsToLocalStorage($agency, $batchSize);
        
        // Affichage de la progression avec ProgressBar
        $progressBar = new ProgressBar($io, $results['total_equipments']);
        $progressBar->setFormat('verbose');
        $progressBar->start();

        // Simuler la progression basée sur les résultats
        for ($i = 0; $i < $results['processed']; $i++) {
            $progressBar->advance();
            
            // Petite pause pour voir la progression (à retirer en production)
            if ($i % 10 === 0) {
                usleep(50000); // 0.05 secondes
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        // Vérification de l'intégrité si demandée
        if ($verify && $results['migrated'] > 0) {
            $io->info('🔍 Vérification de l\'intégrité des images...');
            $this->verifyImageIntegrity($agency, $io);
        }

        return $results;
    }

    private function verifyImageIntegrity(string $agency, SymfonyStyle $io): void
    {
        $storageStats = $this->imageStorageService->getStorageStats();
        $agencyStats = $storageStats['agencies'][$agency] ?? null;

        if (!$agencyStats) {
            $io->warning('Aucune photo trouvée pour vérification');
            return;
        }

        $verified = 0;
        $corrupted = 0;

        // Récupérer toutes les images de l'agence
        $agenceDir = $this->imageStorageService->getBaseImagePath() . $agency;
        
        if (is_dir($agenceDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($agenceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'jpg') {
                    if ($this->imageStorageService->verifyImageIntegrity($file->getPathname())) {
                        $verified++;
                    } else {
                        $corrupted++;
                        $io->note("Image corrompue détectée: " . $file->getFilename());
                    }
                }
            }
        }

        $io->definitionList(
            ['Images vérifiées' => $verified],
            ['Images corrompues' => $corrupted]
        );

        if ($corrupted > 0) {
            $io->warning("$corrupted image(s) corrompue(s) détectée(s)");
        } else {
            $io->success('Toutes les images sont intègres');
        }
    }

    private function displayPostMigrationTips(SymfonyStyle $io, string $agency, array $report): void
    {
        $io->section('💡 Conseils post-migration');

        $tips = [];

        if ($report['migration_percentage'] >= 90) {
            $tips[] = "✅ Vous pouvez maintenant utiliser les méthodes optimisées pour la génération des PDFs";
            $tips[] = "🔄 Pensez à programmer un nettoyage périodique des photos orphelines";
        }

        if ($report['equipments_without_local_photos'] > 0) {
            $tips[] = "⚠️  {$report['equipments_without_local_photos']} équipements n'ont pas de photos locales";
            $tips[] = "🔍 Vérifiez les logs pour identifier les causes des échecs";
        }

        $tips[] = "📊 Utilisez 'php bin/console app:photo-stats {$agency}' pour les statistiques détaillées";
        $tips[] = "🧹 Programmez 'php bin/console app:clean-photos {$agency}' en tâche cron hebdomadaire";

        $io->listing($tips);

        // Commandes utiles
        $io->section('🛠️  Commandes utiles');
        $io->text([
            "Rapport détaillé: <comment>GET /api/maintenance/photo-migration-report/{$agency}</comment>",
            "Nettoyage orphelins: <comment>GET /api/maintenance/clean-orphaned-photos/{$agency}</comment>",
            "Statistiques stockage: <comment>GET /api/maintenance/storage-stats</comment>",
            "Re-migration forcée: <comment>php bin/console app:migrate-photos {$agency} --force</comment>"
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Méthode manquante pour obtenir le chemin de base des images
     */
    private function getBaseImagePath(): string
    {
        return $this->imageStorageService->getBaseImagePath();
    }
}

/**
 * UTILISATION DE LA COMMANDE:
 * 
 * Migration basique:
 * php bin/console app:migrate-photos S140
 * 
 * Migration avec options:
 * php bin/console app:migrate-photos S140 --batch-size=25 --verify --clean-orphans
 * 
 * Test sans modification:
 * php bin/console app:migrate-photos S140 --dry-run
 * 
 * Re-migration forcée:
 * php bin/console app:migrate-photos S140 --force
 * 
 * PROGRAMMATION CRON RECOMMANDÉE:
 * 
 * # Migration quotidienne des nouvelles photos
 * 0 2 * * * cd /path/to/project && php bin/console app:migrate-photos S140 >> /var/log/photo-migration.log 2>&1
 * 
 * # Nettoyage hebdomadaire des orphelins
 * 0 3 * * 0 cd /path/to/project && php bin/console app:migrate-photos S140 --clean-orphans >> /var/log/photo-cleanup.log 2>&1
 */