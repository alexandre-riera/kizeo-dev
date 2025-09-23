<?php
// src/Command/DownloadMissingPhotosCommand.php

namespace App\Command;

use App\Service\PhotoManagementService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:download-missing-photos',
    description: 'Télécharge les photos manquantes pour un client depuis l\'API Kizeo',
)]
class DownloadMissingPhotosCommand extends Command
{
    private PhotoManagementService $photoManagementService;

    public function __construct(PhotoManagementService $photoManagementService)
    {
        $this->photoManagementService = $photoManagementService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('client', InputArgument::REQUIRED, 'Nom du client (ex: PEDRETTI BUCHERES)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans téléchargement')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $clientName = $input->getArgument('client');
        $dryRun = $input->getOption('dry-run');

        $io->title("Téléchargement des photos manquantes pour {$clientName}");

        if ($dryRun) {
            $io->warning('Mode simulation activé - aucune photo ne sera téléchargée');
        }

        try {
            if ($dryRun) {
                // TODO: Implémenter la logique de simulation
                $io->info('Simulation non implémentée pour le moment');
                return Command::SUCCESS;
            }

            $results = $this->photoManagementService->downloadMissingPhotosForClient($clientName);

            $io->section('Résultats');
            $io->success("Photos téléchargées : {$results['downloaded']}");
            $io->info("Photos ignorées (déjà présentes) : {$results['skipped']}");
            
            if ($results['errors'] > 0) {
                $io->error("Erreurs rencontrées : {$results['errors']}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors du téléchargement : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}