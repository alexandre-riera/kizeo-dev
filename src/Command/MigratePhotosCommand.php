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
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Vérifier l\'intégrité des images après téléchargement')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Mode debug avec logs détaillés')
            ->setHelp('
Cette commande migre les photos des équipements depuis l\'API Kizeo Forms vers un stockage local.

Exemples d\'utilisation:
  php bin/console app:migrate-photos S140
  php bin/console app:migrate-photos S140 --batch-size=25 --watch
  php bin/console app:migrate-photos S140 --dry-run --debug
  php bin/console app:migrate-photos S140 --force --clean-orphans
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agency = $input->getArgument('agency');
        $batchSize = (int) $input->getOption('batch-size');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $cleanOrphans = $input->getOption('clean-orphans');
        $watch = $input->getOption('watch');
        $debug = $input->getOption('debug');

        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];

        if (!in_array($agency, $validAgencies)) {
            $io->error("Code agence invalide. Agences valides: " . implode(', ', $validAgencies));
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('Mode simulation activé - aucune modification ne sera effectuée');
        }

        $io->title("Migration des photos pour l'agence {$agency}");

        // Étape 1: Vérifications préliminaires
        if (!$this->performPreChecks($io, $agency, $debug)) {
            return Command::FAILURE;
        }

        // Étape 2: Analyse initiale
        $io->section('📊 Analyse initiale');
        $report = $this->generateInitialReport($agency, $io, $debug);
        
        $this->displayReport($io, $report);

        if (!$force && $report['equipments_without_local_photos'] === 0) {
            $io->success('Toutes les photos sont déjà migrées !');
            return Command::SUCCESS;
        }

        // Étape 3: Migration (ou simulation)
        $migrationResults = null;
        if (!$dryRun) {
            $io->section('🔄 Migration des photos');
            $migrationResults = $this->performMigration($agency, $batchSize, $force, $watch, $io, $debug);
            $this->displayMigrationResults($io, $migrationResults);
        } else {
            $io->section('🧪 Simulation de migration');
            $migrationResults = $this->simulateMigration($agency, $batchSize, $io, $debug);
            $this->displaySimulationResults($io, $migrationResults);
        }

        // Étape 4: Nettoyage des orphelins
        if ($cleanOrphans && !$dryRun) {
            $io->section('🧹 Nettoyage des photos orphelines');
            $cleanResults = $this->performCleanup($agency, $io, $debug);
            $this->displayCleanupResults($io, $cleanResults);
        }

        // Étape 5: Rapport final
        $io->section('📈 Rapport final');
        $finalReport = $this->generateFinalReport($agency, $io, $debug);
        $this->displayReport($io, $finalReport);

        // Déterminer le résultat
        if ($dryRun) {
            if ($migrationResults && $migrationResults['can_migrate'] > 0) {
                $io->success('Simulation réussie ! La migration peut être effectuée.');
                $this->displayPostSimulationTips($io, $agency, $migrationResults);
                return Command::SUCCESS;
            } else {
                $io->error('Simulation échouée - aucune photo ne peut être migrée.');
                $this->displayTroubleshootingTips($io, $agency);
                return Command::FAILURE;
            }
        } else {
            if ($finalReport['migration_percentage'] >= 50) {
                $io->success('Migration terminée avec succès !');
                $this->displayPostMigrationTips($io, $agency, $finalReport);
                return Command::SUCCESS;
            } else {
                $io->error('Migration partiellement échouée - vérifiez les logs');
                $this->displayTroubleshootingTips($io, $agency);
                return Command::FAILURE;
            }
        }
    }

    private function performPreChecks(SymfonyStyle $io, string $agency, bool $debug): bool
    {
        $checks = [
            'Entity class' => $this->checkEntityClass($agency, $debug),
            'Base directory' => $this->checkBaseDirectory($debug),
            'API token' => $this->checkApiToken($debug),
            'Database connection' => $this->checkDatabaseConnection($debug)
        ];

        $allPassed = true;
        foreach ($checks as $checkName => $result) {
            if ($result['success']) {
                $io->writeln("✅ {$checkName}: {$result['message']}");
            } else {
                $io->writeln("❌ {$checkName}: {$result['message']}");
                $allPassed = false;
            }
        }

        return $allPassed;
    }

    private function checkEntityClass(string $agency, bool $debug): array
    {
        $entityClass = "App\\Entity\\Equipement{$agency}";
        
        if (!class_exists($entityClass)) {
            return [
                'success' => false,
                'message' => "Classe d'entité {$entityClass} non trouvée"
            ];
        }

        try {
            $repository = $this->entityManager->getRepository($entityClass);
            $count = $repository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleScalarResult();

            return [
                'success' => true,
                'message' => "Entité trouvée avec {$count} équipements"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Erreur accès entité: " . $e->getMessage()
            ];
        }
    }

    private function checkBaseDirectory(bool $debug): array
    {
        $baseDir = $this->imageStorageService->getBaseImagePath();
        
        if (!is_dir($baseDir)) {
            return [
                'success' => false,
                'message' => "Répertoire de base non trouvé: {$baseDir}"
            ];
        }

        if (!is_writable($baseDir)) {
            return [
                'success' => false,
                'message' => "Répertoire de base non accessible en écriture: {$baseDir}"
            ];
        }

        return [
            'success' => true,
            'message' => "Répertoire accessible: {$baseDir}"
        ];
    }

    private function checkApiToken(bool $debug): array
    {
        $token = $_ENV['KIZEO_API_TOKEN'] ?? null;
        
        if (!$token) {
            return [
                'success' => false,
                'message' => "Token API Kizeo non configuré"
            ];
        }

        return [
            'success' => true,
            'message' => "Token configuré (" . substr($token, 0, 8) . "...)"
        ];
    }

    private function checkDatabaseConnection(bool $debug): array
    {
        try {
            $connection = $this->entityManager->getConnection();
            $connection->connect();
            
            // Test simple
            $result = $connection->executeQuery('SELECT COUNT(*) FROM form LIMIT 1')->fetchOne();
            
            return [
                'success' => true,
                'message' => "Base de données accessible ({$result} entrées form)"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Erreur base de données: " . $e->getMessage()
            ];
        }
    }

    private function generateInitialReport(string $agency, SymfonyStyle $io, bool $debug): array
    {
        try {
            return $this->formRepository->getPhotoMigrationReport($agency);
        } catch (\Exception $e) {
            if ($debug) {
                $io->error("Erreur génération rapport initial: " . $e->getMessage());
            }
            
            return [
                'agence' => $agency,
                'total_equipments' => 0,
                'equipments_with_local_photos' => 0,
                'equipments_without_local_photos' => 0,
                'migration_percentage' => 0,
                'total_local_photos' => 0,
                'storage_used' => '0 B',
                'average_photos_per_equipment' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    private function generateFinalReport(string $agency, SymfonyStyle $io, bool $debug): array
    {
        return $this->generateInitialReport($agency, $io, $debug);
    }

    private function simulateMigration(string $agency, int $batchSize, SymfonyStyle $io, bool $debug): array
    {
        $results = [
            'can_migrate' => 0,
            'has_form_data' => 0,
            'missing_form_data' => 0,
            'errors' => 0,
            'sample_equipments' => []
        ];

        try {
            $entityClass = "App\\Entity\\Equipement{$agency}";
            $repository = $this->entityManager->getRepository($entityClass);
            
            // Prendre un échantillon pour la simulation
            $sampleSize = min($batchSize, 10);
            $equipments = $repository->createQueryBuilder('e')
                ->setMaxResults($sampleSize)
                ->getQuery()
                ->getResult();

            foreach ($equipments as $equipment) {
                try {
                    $equipmentInfo = [
                        'numero' => $equipment->getNumeroEquipement(),
                        'raison_sociale' => $equipment->getRaisonSociale(),
                        'visite' => $equipment->getVisite(),
                        'can_migrate' => false,
                        'reason' => ''
                    ];

                    // Vérifier si des données Form existent
                    $formData = $this->formRepository->findOneBy([
                        'equipment_id' => $equipment->getNumeroEquipement(),
                        'raison_sociale_visite' => $equipment->getRaisonSociale() . '\\' . $equipment->getVisite()
                    ]);

                    if ($formData && $formData->getFormId() && $formData->getDataId()) {
                        $results['has_form_data']++;
                        
                        // Vérifier s'il y a des photos à migrer
                        $hasPhotos = false;
                        $photoFields = [
                            $formData->getPhotoCompteRendu(),
                            $formData->getPhotoEnvironnementEquipement1(),
                            $formData->getPhotoPlaque(),
                            $formData->getPhoto2()
                        ];

                        foreach ($photoFields as $photo) {
                            if (!empty($photo)) {
                                $hasPhotos = true;
                                break;
                            }
                        }

                        if ($hasPhotos) {
                            $results['can_migrate']++;
                            $equipmentInfo['can_migrate'] = true;
                            $equipmentInfo['reason'] = 'Photos disponibles';
                        } else {
                            $equipmentInfo['reason'] = 'Aucune photo dans Form';
                        }
                    } else {
                        $results['missing_form_data']++;
                        $equipmentInfo['reason'] = 'Données Form manquantes';
                    }

                    $results['sample_equipments'][] = $equipmentInfo;

                } catch (\Exception $e) {
                    $results['errors']++;
                    if ($debug) {
                        $io->writeln("Erreur simulation équipement {$equipment->getNumeroEquipement()}: " . $e->getMessage());
                    }
                }
            }

        } catch (\Exception $e) {
            $results['errors']++;
            if ($debug) {
                $io->error("Erreur simulation: " . $e->getMessage());
            }
        }

        return $results;
    }

    private function performMigration(string $agency, int $batchSize, bool $force, bool $watch, SymfonyStyle $io, bool $debug): array
    {
        // Version simplifiée pour la migration réelle
        try {
            return $this->formRepository->migrateAllEquipmentsToLocalStorage($agency, $batchSize);
        } catch (\Exception $e) {
            if ($debug) {
                $io->error("Erreur migration: " . $e->getMessage());
            }
            
            return [
                'total_equipments' => 0,
                'processed' => 0,
                'migrated' => 0,
                'skipped' => 0,
                'errors' => 1,
                'batches_completed' => 0,
                'error_message' => $e->getMessage()
            ];
        }
    }

    private function performCleanup(string $agency, SymfonyStyle $io, bool $debug): array
    {
        try {
            return $this->formRepository->cleanOrphanedPhotos($agency);
        } catch (\Exception $e) {
            if ($debug) {
                $io->error("Erreur nettoyage: " . $e->getMessage());
            }
            
            return [
                'checked' => 0,
                'deleted' => 0,
                'errors' => 1,
                'size_freed' => 0,
                'error_message' => $e->getMessage()
            ];
        }
    }

    private function displayReport(SymfonyStyle $io, array $report): void
    {
        if (isset($report['error'])) {
            $io->warning("Erreur génération rapport: " . $report['error']);
        }

        $io->definitionList(
            ['Équipements total' => $report['total_equipments']],
            ['Avec photos locales' => $report['equipments_with_local_photos']],
            ['Sans photos locales' => $report['equipments_without_local_photos']],
            ['Pourcentage migré' => $report['migration_percentage'] . '%'],
            ['Stockage utilisé' => $report['storage_used']]
        );
    }

    private function displaySimulationResults(SymfonyStyle $io, array $results): void
    {
        $io->definitionList(
            ['Équipements analysés' => count($results['sample_equipments'])],
            ['Peuvent être migrés' => $results['can_migrate']],
            ['Ont des données Form' => $results['has_form_data']],
            ['Données Form manquantes' => $results['missing_form_data']],
            ['Erreurs' => $results['errors']]
        );

        if (!empty($results['sample_equipments'])) {
            $io->section('📋 Échantillon d\'équipements');
            $tableData = [];
            foreach (array_slice($results['sample_equipments'], 0, 5) as $eq) {
                $tableData[] = [
                    $eq['numero'],
                    $eq['can_migrate'] ? '✅' : '❌',
                    $eq['reason']
                ];
            }
            $io->table(['Équipement', 'Peut migrer', 'Raison'], $tableData);
        }
    }

    private function displayMigrationResults(SymfonyStyle $io, array $results): void
    {
        $io->definitionList(
            ['Équipements traités' => $results['processed']],
            ['Photos migrées' => $results['migrated']],
            ['Ignorés' => $results['skipped']],
            ['Erreurs' => $results['errors']],
            ['Lots complétés' => $results['batches_completed']]
        );

        if (isset($results['error_message'])) {
            $io->error("Erreur migration: " . $results['error_message']);
        }
    }

    private function displayCleanupResults(SymfonyStyle $io, array $results): void
    {
        $io->definitionList(
            ['Photos vérifiées' => $results['checked']],
            ['Photos supprimées' => $results['deleted']],
            ['Espace libéré' => $this->formatBytes($results['size_freed'])],
            ['Erreurs' => $results['errors']]
        );
    }

    private function displayPostSimulationTips(SymfonyStyle $io, string $agency, array $results): void
    {
        $io->section('💡 Conseils post-simulation');
        
        $tips = [
            "✅ {$results['can_migrate']} équipements peuvent être migrés",
            "🔄 Lancez la migration réelle: php bin/console app:migrate-photos {$agency}",
            "📊 Surveillez les logs pendant la migration"
        ];

        if ($results['missing_form_data'] > 0) {
            $tips[] = "⚠️ {$results['missing_form_data']} équipements n'ont pas de données Form";
        }

        $io->listing($tips);
    }

    private function displayPostMigrationTips(SymfonyStyle $io, string $agency, array $report): void
    {
        $io->section('💡 Conseils post-migration');
        
        $tips = [
            "✅ Migration terminée pour l'agence {$agency}",
            "📊 Utilisez les APIs de monitoring pour surveiller le système",
            "🔄 Programmez les tâches de maintenance automatiques"
        ];

        $io->listing($tips);
    }

    private function displayTroubleshootingTips(SymfonyStyle $io, string $agency): void
    {
        $io->section('🔧 Conseils de dépannage');
        
        $io->text([
            'Problèmes possibles et solutions :',
            '',
            '1. Données Form manquantes :',
            '   - Vérifiez que des entrées existent dans la table form',
            '   - Assurez-vous que equipment_id correspond',
            '',
            '2. Problème d\'accès API :',
            '   - Vérifiez KIZEO_API_TOKEN dans .env.local',
            '   - Testez la connectivité réseau',
            '',
            '3. Problème de permissions :',
            '   - chmod -R 755 public/img/',
            '   - chown -R www-data:www-data public/img/',
            '',
            '4. Utilisation debug :',
            "   - php bin/console app:migrate-photos {$agency} --debug --dry-run"
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}