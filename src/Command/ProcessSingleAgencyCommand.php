<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:process-single-agency',
    description: 'Traite une seule agence avec des micro-batches pour éviter les timeouts'
)]
class ProcessSingleAgencyCommand extends Command
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence (ex: S10, S40...)')
            ->addOption('chunk-size', 'c', InputOption::VALUE_OPTIONAL, 'Taille des chunks', 5)
            ->addOption('max-submissions', 'm', InputOption::VALUE_OPTIONAL, 'Nombre max de submissions', 50)
            ->addOption('photos-only', 'p', InputOption::VALUE_NONE, 'Migration photos uniquement')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Test sans écriture en base')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agency = $input->getArgument('agency');
        $chunkSize = $input->getOption('chunk-size');
        $maxSubmissions = $input->getOption('max-submissions');
        $photosOnly = $input->getOption('photos-only');
        $dryRun = $input->getOption('dry-run');

        $io->title("🚀 Traitement agence $agency");
        
        if ($dryRun) {
            $io->note('Mode DRY-RUN activé - Aucune donnée ne sera modifiée');
        }

        $io->section('Configuration');
        $io->listing([
            "Agence: $agency",
            "Chunk size: $chunkSize",
            "Max submissions: $maxSubmissions",
            "Photos uniquement: " . ($photosOnly ? 'OUI' : 'NON'),
            "Dry run: " . ($dryRun ? 'OUI' : 'NON')
        ]);

        try {
            if ($photosOnly) {
                return $this->processPhotosOnly($io, $agency, $dryRun);
            } else {
                return $this->processFullMigration($io, $agency, $chunkSize, $maxSubmissions, $dryRun);
            }

        } catch (\Exception $e) {
            $io->error('Erreur lors du traitement: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function processFullMigration(SymfonyStyle $io, string $agency, int $chunkSize, int $maxSubmissions, bool $dryRun): int
    {
        $io->section('Étape 1: Traitement des équipements');
        
        // Construction de l'URL avec paramètres optimisés
        $baseUrl = 'https://backend-kizeo.somafi-group.fr/api/maintenance/process-fixed/';
        $params = [
            'chunk_size' => $chunkSize,
            'max_submissions' => $maxSubmissions,
            'use_cache' => 'false',
            'migrate_photos' => 'false', // Séparer la migration photos
            'dry_run' => $dryRun ? 'true' : 'false'
        ];
        
        $url = $baseUrl . $agency . '?' . http_build_query($params);
        
        $io->text("Appel API: $url");
        
        $progressBar = $io->createProgressBar();
        $progressBar->start();
        
        try {
            $response = $this->client->request('GET', $url, [
                'timeout' => 120, // 2 minutes max
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Symfony-Emergency-Command'
                ]
            ]);

            $progressBar->finish();
            $io->newLine(2);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                $io->error("Erreur HTTP $statusCode");
                return Command::FAILURE;
            }

            $content = $response->toArray();
            
            // Afficher les résultats
            $io->success('✅ Traitement équipements terminé avec succès');
            
            if (isset($content['processing_summary'])) {
                $summary = $content['processing_summary'];
                
                $io->section('📊 Résumé du traitement');
                $io->table(
                    ['Métrique', 'Valeur'],
                    [
                        ['Formulaires traités', $summary['processed_submissions'] ?? '0'],
                        ['Équipements processés', $summary['total_equipments_processed'] ?? '0'],
                        ['Temps de traitement', $summary['processing_time'] ?? 'N/A'],
                        ['Erreurs', isset($content['errors']) ? count($content['errors']) : '0']
                    ]
                );
            }

            // Étape 2: Vérification et photos
            return $this->processPhotosStep($io, $agency, $dryRun);

        } catch (\Exception $e) {
            $progressBar->finish();
            $io->newLine(2);
            $io->error('Erreur lors du traitement: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function processPhotosStep(SymfonyStyle $io, string $agency, bool $dryRun): int
    {
        $io->section('Étape 2: Vérification et migration des photos');

        // Vérifier combien d'équipements n'ont pas de photos
        try {
            $checkUrl = "https://backend-kizeo.somafi-group.fr/api/maintenance/check-photos/$agency";
            
            $response = $this->client->request('GET', $checkUrl, [
                'timeout' => 30,
                'headers' => ['Accept' => 'application/json']
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                
                $withPhotos = $data['equipments_with_photos'] ?? 0;
                $withoutPhotos = $data['equipments_without_photos'] ?? 0;
                $total = $withPhotos + $withoutPhotos;
                
                $io->table(
                    ['État', 'Nombre'],
                    [
                        ['Total équipements', $total],
                        ['Avec photos', $withPhotos],
                        ['Sans photos', $withoutPhotos]
                    ]
                );

                if ($withoutPhotos > 0 && !$dryRun) {
                    $io->text("🔄 Migration de $withoutPhotos photos manquantes...");
                    return $this->processPhotosOnly($io, $agency, $dryRun);
                } else {
                    $io->success('✅ Toutes les photos sont déjà migrées !');
                    return Command::SUCCESS;
                }
            }

        } catch (\Exception $e) {
            $io->warning('Impossible de vérifier les photos: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }

    private function processPhotosOnly(SymfonyStyle $io, string $agency, bool $dryRun): int
    {
        $io->section('🖼️ Migration spécifique des photos');
        
        if ($dryRun) {
            $io->note('Mode dry-run - Simulation de la migration photos');
            return Command::SUCCESS;
        }
        
        $url = "https://backend-kizeo.somafi-group.fr/api/maintenance/migrate-photos/$agency?batch_size=20&only_missing=true";
        
        $progressBar = $io->createProgressBar();
        $progressBar->start();
        
        try {
            $response = $this->client->request('GET', $url, [
                'timeout' => 300, // 5 minutes pour les photos
                'headers' => ['Accept' => 'application/json']
            ]);

            $progressBar->finish();
            $io->newLine(2);

            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                
                // Extraire le nombre de photos migrées depuis la réponse
                if (preg_match('/Migrés avec succès[^0-9]*([0-9]+)/', $content, $matches)) {
                    $photosMigrated = $matches[1];
                    $io->success("✅ $photosMigrated photos migrées avec succès !");
                } else {
                    $io->success('✅ Migration photos terminée');
                }
                
                return Command::SUCCESS;
            } else {
                $io->error('Erreur lors de la migration photos');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $progressBar->finish();
            $io->newLine(2);
            $io->error('Erreur migration photos: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}