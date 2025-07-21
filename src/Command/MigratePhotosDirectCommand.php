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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\FormRepository;
use App\Service\ImageStorageService;

#[AsCommand(
    name: 'app:migrate-photos-direct',
    description: 'Migration des photos en utilisant uniquement equipment_id (méthode corrigée)'
)]
class MigratePhotosDirectCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormRepository $formRepository,
        private ImageStorageService $imageStorageService,
        private HttpClientInterface $client
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S140, S50, etc.)')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Taille des lots', 10)
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Simulation sans modification')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer le re-téléchargement des photos existantes')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Mode verbose avec logs détaillés')
            ->setHelp('
Cette commande migre les photos en utilisant uniquement equipment_id pour résoudre 
les problèmes de correspondance raison_sociale_visite.

Exemples:
  php bin/console app:migrate-photos-direct S140 --dry-run
  php bin/console app:migrate-photos-direct S140 --batch-size=5 --debug
  php bin/console app:migrate-photos-direct S140 --force
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agency = $input->getArgument('agency');
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $verbose = $input->getOption('verbose');

        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];

        if (!in_array($agency, $validAgencies)) {
            $io->error("Code agence invalide. Agences valides: " . implode(', ', $validAgencies));
            return Command::FAILURE;
        }

        $io->title("🔧 Migration directe des photos pour l'agence {$agency}");

        if ($dryRun) {
            $io->note('Mode simulation activé - aucune modification ne sera effectuée');
        }

        // Vérifications préliminaires
        if (!$this->performPreChecks($io, $agency)) {
            return Command::FAILURE;
        }

        try {
            // Récupérer les équipements
            $entityClass = "App\\Entity\\Equipement{$agency}";
            $repository = $this->entityManager->getRepository($entityClass);
            
            $totalEquipments = $repository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $io->writeln("📊 Total équipements à traiter: {$totalEquipments}");

            $results = [
                'processed' => 0,
                'migrated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'photos_downloaded' => 0
            ];

            // Traiter par lots
            $offset = 0;
            $progressBar = new ProgressBar($io, $totalEquipments);
            $progressBar->setFormat('verbose');
            $progressBar->start();

            while ($offset < $totalEquipments) {
                $equipments = $repository->createQueryBuilder('e')
                    ->setFirstResult($offset)
                    ->setMaxResults($batchSize)
                    ->getQuery()
                    ->getResult();

                foreach ($equipments as $equipment) {
                    $equipmentResult = $this->processEquipment($equipment, $agency, $dryRun, $force, $verbose, $io);
                    
                    $results['processed']++;
                    $results[$equipmentResult['status']]++;
                    $results['photos_downloaded'] += $equipmentResult['photos_downloaded'];

                    $progressBar->advance();

                    if ($verbose) {
                        $io->writeln("\n  {$equipment->getNumeroEquipement()}: {$equipmentResult['message']}");
                    }
                }

                $offset += $batchSize;

                // Nettoyer la mémoire
                $this->entityManager->clear();
                gc_collect_cycles();
            }

            $progressBar->finish();
            $io->newLine(2);

            // Afficher les résultats
            $this->displayResults($io, $results, $dryRun);

            // Déterminer le succès
            if ($results['migrated'] > 0 || ($dryRun && $results['migrated'] > 0)) {
                $this->displaySuccessTips($io, $agency, $results, $dryRun);
                return Command::SUCCESS;
            } else {
                $this->displayFailureTips($io, $agency, $results);
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error("❌ Erreur de migration: " . $e->getMessage());
            if ($verbose) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function performPreChecks(SymfonyStyle $io, string $agency): bool
    {
        $checks = [
            'API Token' => !empty($_ENV['KIZEO_API_TOKEN']),
            'Base Directory' => is_dir($this->imageStorageService->getBaseImagePath()),
            'Entity Class' => class_exists("App\\Entity\\Equipement{$agency}")
        ];

        $allPassed = true;
        foreach ($checks as $checkName => $passed) {
            if ($passed) {
                $io->writeln("✅ {$checkName}");
            } else {
                $io->writeln("❌ {$checkName}");
                $allPassed = false;
            }
        }

        return $allPassed;
    }

    private function processEquipment($equipment, string $agency, bool $dryRun, bool $force, bool $verbose, SymfonyStyle $io): array
    {
        $equipmentId = $equipment->getNumeroEquipement();
        
        $result = [
            'status' => 'skipped',
            'message' => 'Non traité',
            'photos_downloaded' => 0
        ];

        try {
            // 1. Chercher les données Form par equipment_id UNIQUEMENT
            $formData = $this->formRepository->findOneBy([
                'equipment_id' => $equipmentId
            ]);

            if (!$formData) {
                $result['message'] = 'Aucune donnée Form trouvée';
                return $result;
            }

            // 2. Vérifier les données API
            if (!$formData->getFormId() || !$formData->getDataId()) {
                $result['message'] = 'Données API manquantes (Form ID ou Data ID)';
                return $result;
            }

            // 3. Inventorier les photos disponibles
            $availablePhotos = $this->getAvailablePhotos($formData);

            if (empty($availablePhotos)) {
                $result['message'] = 'Aucune photo disponible';
                return $result;
            }

            // 4. Vérifier si déjà migré (sauf si force)
            if (!$force && $this->isAlreadyMigrated($equipment, $agency, $availablePhotos)) {
                $result['message'] = 'Photos déjà migrées';
                return $result;
            }

            // 5. Migration des photos
            if (!$dryRun) {
                $downloadedCount = $this->downloadPhotos($equipment, $formData, $agency, $availablePhotos, $verbose);
                $result['photos_downloaded'] = $downloadedCount;
                
                if ($downloadedCount > 0) {
                    $result['status'] = 'migrated';
                    $result['message'] = "{$downloadedCount} photos téléchargées";
                } else {
                    $result['status'] = 'errors';
                    $result['message'] = 'Échec téléchargement photos';
                }
            } else {
                $result['status'] = 'migrated';
                $result['message'] = count($availablePhotos) . ' photos peuvent être téléchargées';
                $result['photos_downloaded'] = count($availablePhotos);
            }

        } catch (\Exception $e) {
            $result['status'] = 'errors';
            $result['message'] = 'Erreur: ' . $e->getMessage();
        }

        return $result;
    }

    private function getAvailablePhotos($formData): array
    {
        $photos = [];

        $photoMappings = [
            'compte_rendu' => $formData->getPhotoCompteRendu(),
            'environnement' => $formData->getPhotoEnvironnementEquipement1(),
            'plaque' => $formData->getPhotoPlaque(),
            'etiquette_somafi' => $formData->getPhotoEtiquetteSomafi(),
            'generale' => $formData->getPhoto2(),
            'moteur' => $formData->getPhotoMoteur(),
            'carte' => $formData->getPhotoCarte(),
            'choc' => $formData->getPhotoChoc(),
            'rail' => $formData->getPhotoRail()
        ];

        foreach ($photoMappings as $type => $photoName) {
            if (!empty($photoName)) {
                $photos[$type] = $photoName;
            }
        }

        return $photos;
    }

    private function isAlreadyMigrated($equipment, string $agency, array $availablePhotos): bool
    {
        $raisonSociale = $this->cleanFileName(explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale());
        $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
        $typeVisite = $equipment->getVisite();
        $codeEquipement = $equipment->getNumeroEquipement();

        // Vérifier si au moins une photo existe localement
        foreach (array_keys($availablePhotos) as $photoType) {
            $filename = $codeEquipement . '_' . $photoType;
            if ($this->imageStorageService->imageExists($agency, $raisonSociale, $anneeVisite, $typeVisite, $filename)) {
                return true;
            }
        }

        return false;
    }

    private function downloadPhotos($equipment, $formData, string $agency, array $availablePhotos, bool $verbose): int
    {
        $raisonSociale = $this->cleanFileName(explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale());
        $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
        $typeVisite = $equipment->getVisite();
        $codeEquipement = $equipment->getNumeroEquipement();

        $downloadedCount = 0;

        foreach ($availablePhotos as $photoType => $photoName) {
            try {
                // Gérer les photos multiples (séparées par des virgules)
                $photoNames = str_contains($photoName, ', ') ? explode(', ', $photoName) : [$photoName];
                
                foreach ($photoNames as $index => $singlePhotoName) {
                    $singlePhotoName = trim($singlePhotoName);
                    if (empty($singlePhotoName)) continue;

                    $filename = count($photoNames) > 1 
                        ? $codeEquipement . '_' . $photoType . '_' . ($index + 1)
                        : $codeEquipement . '_' . $photoType;

                    // Vérifier si existe déjà
                    if ($this->imageStorageService->imageExists($agency, $raisonSociale, $anneeVisite, $typeVisite, $filename)) {
                        if ($verbose) {
                            error_log("Photo existe déjà: {$filename}");
                        }
                        continue;
                    }

                    // Télécharger depuis l'API Kizeo
                    $imageContent = $this->downloadFromKizeoApi($formData->getFormId(), $formData->getDataId(), $singlePhotoName, $verbose);
                    
                    if ($imageContent) {
                        // Sauvegarder localement
                        $this->imageStorageService->storeImage(
                            $agency,
                            $raisonSociale,
                            $anneeVisite,
                            $typeVisite,
                            $filename,
                            $imageContent
                        );
                        
                        $downloadedCount++;
                        
                        if ($verbose) {
                            error_log("Photo téléchargée: {$filename} (" . strlen($imageContent) . " octets)");
                        }
                    }
                }

            } catch (\Exception $e) {
                if ($verbose) {
                    error_log("Erreur téléchargement {$photoType}: " . $e->getMessage());
                }
            }
        }

        return $downloadedCount;
    }

    private function downloadFromKizeoApi(string $formId, string $dataId, string $photoName, bool $verbose): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/{$dataId}/medias/{$photoName}",
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 30
                ]
            );

            $imageContent = $response->getContent();
            
            if (empty($imageContent)) {
                if ($verbose) {
                    error_log("Contenu vide pour photo: {$photoName}");
                }
                return null;
            }

            return $imageContent;

        } catch (\Exception $e) {
            if ($verbose) {
                error_log("Erreur API Kizeo pour {$photoName}: " . $e->getMessage());
            }
            return null;
        }
    }

    private function cleanFileName(string $name): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name);
        $cleaned = preg_replace('/_+/', '_', $cleaned);
        return trim($cleaned, '_');
    }

    private function displayResults(SymfonyStyle $io, array $results, bool $dryRun): void
    {
        $io->section('📊 Résultats de migration');
        
        $io->definitionList(
            ['Équipements traités' => $results['processed']],
            ['Migrés avec succès' => $results['migrated']],
            ['Ignorés (déjà migrés)' => $results['skipped']],
            ['Erreurs' => $results['errors']],
            ['Photos téléchargées' => $results['photos_downloaded']]
        );

        $successRate = $results['processed'] > 0 
            ? round(($results['migrated'] / $results['processed']) * 100, 1) 
            : 0;

        $io->writeln("📈 Taux de succès: {$successRate}%");
    }

    private function displaySuccessTips(SymfonyStyle $io, string $agency, array $results, bool $dryRun): void
    {
        $io->success($dryRun 
            ? "🎉 Simulation réussie - {$results['migrated']} équipements peuvent être migrés"
            : "🎉 Migration réussie - {$results['migrated']} équipements migrés avec {$results['photos_downloaded']} photos"
        );

        if (!$dryRun) {
            $io->section('✨ Prochaines étapes');
            $io->listing([
                "Vérifiez le rapport: curl \"http://localhost/api/maintenance/photo-migration-report/{$agency}\"",
                "Testez la génération PDF optimisée avec les nouvelles photos locales",
                "Programmez la migration quotidienne pour les nouveaux équipements",
                "Mise à jour de vos contrôleurs PDF pour utiliser les méthodes optimisées"
            ]);
        } else {
            $io->section('✨ Migration réelle');
            $io->writeln("Lancez la migration réelle:");
            $io->writeln("  <comment>php bin/console app:migrate-photos-direct {$agency} --batch-size=10</comment>");
        }
    }

    private function displayFailureTips(SymfonyStyle $io, string $agency, array $results): void
    {
        $io->error("❌ Migration échouée - aucun équipement migré");
        
        $io->section('🔧 Conseils de dépannage');
        
        if ($results['errors'] > 0) {
            $io->writeln("• Activez le mode verbose: --verbose");
            $io->writeln("• Vérifiez les logs d'erreurs");
        }
        
        if ($results['skipped'] > 0) {
            $io->writeln("• Utilisez --force pour re-télécharger les photos existantes");
        }
        
        $io->writeln("• Vérifiez la connectivité réseau vers l'API Kizeo");
        $io->writeln("• Vérifiez le token KIZEO_API_TOKEN");
    }
}

/**
 * UTILISATION DE LA COMMANDE CORRIGÉE :
 * 
 * # Test en mode simulation (recommandé)
 * php bin/console app:migrate-photos-direct S140 --dry-run --batch-size=5
 * 
 * # Migration réelle avec verbose
 * php bin/console app:migrate-photos-direct S140 --batch-size=5 --verbose
 * 
 * # Migration complète
 * php bin/console app:migrate-photos-direct S140 --batch-size=20
 * 
 * # Forcer le re-téléchargement
 * php bin/console app:migrate-photos-direct S140 --force
 * 
 * Cette version corrigée utilise uniquement equipment_id pour éviter les problèmes 
 * de correspondance raison_sociale_visite identifiés lors du diagnostic.
 */