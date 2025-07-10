<?php

namespace App\Command;

use App\Service\ImageStorageService;
use App\Controller\SimplifiedMaintenanceController;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:download-images',
    description: 'Télécharge et met en cache les images des équipements depuis Kizeo'
)]
class DownloadImagesCommand extends Command
{
    private ImageStorageService $imageStorageService;
    private SimplifiedMaintenanceController $maintenanceController;
    private LoggerInterface $logger;

    public function __construct(
        ImageStorageService $imageStorageService,
        SimplifiedMaintenanceController $maintenanceController,
        LoggerInterface $logger
    ) {
        $this->imageStorageService = $imageStorageService;
        $this->maintenanceController = $maintenanceController;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force le téléchargement même si les images existent déjà')
            ->addOption('agency', 'a', InputOption::VALUE_OPTIONAL, 'Télécharger uniquement pour une agence spécifique (ex: S10)')
            ->addOption('client', 'c', InputOption::VALUE_OPTIONAL, 'Télécharger uniquement pour un client spécifique')
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Nettoyer les répertoires vides après téléchargement')
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Afficher uniquement les statistiques de stockage')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Affichage des statistiques uniquement
        if ($input->getOption('stats')) {
            return $this->showStats($io);
        }

        $force = $input->getOption('force');
        $agency = $input->getOption('agency');
        $client = $input->getOption('client');
        $cleanup = $input->getOption('cleanup');

        $io->title('Téléchargement des images d\'équipements depuis Kizeo');

        if ($force) {
            $io->note('Mode forcé activé : les images existantes seront remplacées');
        }

        if ($agency) {
            $io->note("Filtrage par agence : {$agency}");
        }

        if ($client) {
            $io->note("Filtrage par client : {$client}");
        }

        try {
            // Lancer le téléchargement
            $io->section('Démarrage du téléchargement...');
            
            $startTime = microtime(true);
            
            // Appeler la méthode du controller (vous devrez peut-être l'adapter)
            $result = $this->downloadImagesWithOptions($force, $agency, $client);
            
            $duration = round(microtime(true) - $startTime, 2);
            
            // Afficher les résultats
            $io->section('Résultats du téléchargement');
            
            $io->horizontalTable(
                ['Métrique', 'Valeur'],
                [
                    ['Images téléchargées', $result['downloaded']],
                    ['Images déjà présentes', $result['existing']],
                    ['Erreurs', $result['errors']],
                    ['Durée totale', "{$duration}s"],
                ]
            );

            if ($result['errors'] > 0) {
                $io->warning("Des erreurs sont survenues lors du téléchargement. Consultez les logs pour plus de détails.");
            }

            // Nettoyage optionnel
            if ($cleanup) {
                $io->section('Nettoyage des répertoires vides...');
                $removedDirs = $this->imageStorageService->cleanEmptyDirectories($agency);
                $io->success("Nettoyage terminé : {$removedDirs} répertoires vides supprimés");
            }

            // Afficher les statistiques finales
            $this->showStats($io);

            $io->success('Téléchargement terminé avec succès !');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Erreur lors du téléchargement : " . $e->getMessage());
            $this->logger->error("Erreur commande download-images : " . $e->getMessage());
            
            return Command::FAILURE;
        }
    }

    /**
     * Télécharge les images avec les options spécifiées
     */
    private function downloadImagesWithOptions(bool $force, ?string $agency, ?string $client): array
    {
        $result = [
            'downloaded' => 0,
            'existing' => 0,
            'errors' => 0
        ];

        // Récupérer les équipements selon les filtres
        $equipements = $this->getFilteredEquipements($agency, $client);

        foreach ($equipements as $equipement) {
            try {
                $agence = $this->extractAgenceFromEquipement($equipement);
                $raisonSociale = $this->extractRaisonSocialeFromEquipement($equipement);
                $annee = $this->extractAnneeFromEquipement($equipement);
                $typeVisite = $this->extractTypeVisiteFromEquipement($equipement);
                $codeEquipement = $equipement->getCodeEquipement();

                // Vérifier si l'image existe déjà
                $imageExists = $this->imageStorageService->imageExists(
                    $agence, $raisonSociale, $annee, $typeVisite, $codeEquipement
                );

                if ($imageExists && !$force) {
                    $result['existing']++;
                    continue;
                }

                // Télécharger l'image depuis Kizeo
                $success = $this->downloadImageForEquipement($equipement, $force);
                
                if ($success) {
                    $result['downloaded']++;
                } else {
                    $result['errors']++;
                }

            } catch (\Exception $e) {
                $this->logger->error("Erreur téléchargement image pour équipement {$equipement->getCodeEquipement()}: " . $e->getMessage());
                $result['errors']++;
            }
        }

        return $result;
    }

    /**
     * Télécharge l'image pour un équipement spécifique
     */
    private function downloadImageForEquipement($equipement, bool $force = false): bool
    {
        // Cette méthode devrait utiliser la même logique que dans SimplifiedMaintenanceController
        // Pour l'instant, on retourne un placeholder
        // Vous devrez implémenter cette méthode selon votre structure Kizeo
        
        try {
            // Logique de téléchargement à implémenter
            // return $this->maintenanceController->downloadImageForEquipement($equipement);
            
            // Placeholder pour l'exemple
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur téléchargement image: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les équipements filtrés
     */
    private function getFilteredEquipements(?string $agency, ?string $client): array
    {
        // Cette méthode doit être adaptée selon votre structure
        // Pour l'instant, retourne un tableau vide
        // Vous devrez implémenter cette méthode selon votre repository
        
        return [];
    }

    /**
     * Affiche les statistiques de stockage
     */
    private function showStats(SymfonyStyle $io): int
    {
        $io->section('Statistiques de stockage des images');

        try {
            $stats = $this->imageStorageService->getStorageStats();

            $io->horizontalTable(
                ['Métrique', 'Valeur'],
                [
                    ['Total images', number_format($stats['total_images'])],
                    ['Taille totale', $this->formatBytes($stats['total_size'])],
                    ['Nombre d\'agences', count($stats['agencies'])],
                ]
            );

            if (!empty($stats['agencies'])) {
                $io->section('Répartition par agence');
                
                $agencyData = [];
                foreach ($stats['agencies'] as $agency => $count) {
                    $agencyData[] = [$agency, number_format($count)];
                }
                
                $io->table(['Agence', 'Nombre d\'images'], $agencyData);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Erreur lors de la récupération des statistiques : " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Formate la taille en octets en format lisible
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Méthodes utilitaires (à adapter selon votre modèle)
     */
    private function extractAgenceFromEquipement($equipement): string
    {
        $className = get_class($equipement);
        if (preg_match('/EquipementS(\d+)/', $className, $matches)) {
            return 'S' . $matches[1];
        }
        return 'UNKNOWN';
    }

    private function extractRaisonSocialeFromEquipement($equipement): string
    {
        return $equipement->getRaisonSociale() ?? 'UNKNOWN';
    }

    private function extractAnneeFromEquipement($equipement): string
    {
        $date = $equipement->getDateVisite() ?? $equipement->getCreatedAt();
        return $date ? $date->format('Y') : date('Y');
    }

    private function extractTypeVisiteFromEquipement($equipement): string
    {
        return $equipement->getTypeVisite() ?? 'CE1';
    }
}