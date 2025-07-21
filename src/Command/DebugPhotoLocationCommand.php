<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ImageStorageService;

#[AsCommand(
    name: 'app:debug-photo-location',
    description: 'Debug où sont stockées les photos migrées'
)]
class DebugPhotoLocationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImageStorageService $imageStorageService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('agency', InputArgument::REQUIRED, 'Code agence');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agency = $input->getArgument('agency');

        $io->title("🔍 Debug localisation des photos pour l'agence {$agency}");

        // 1. Vérifier le chemin de base
        $basePath = $this->imageStorageService->getBaseImagePath();
        $io->writeln("📁 Chemin de base: {$basePath}");

        // 2. Chercher toutes les photos dans le répertoire de l'agence
        $agencyPath = $basePath . $agency;
        $io->writeln("📁 Répertoire agence: {$agencyPath}");

        if (!is_dir($agencyPath)) {
            $io->error("❌ Répertoire de l'agence non trouvé");
            return Command::FAILURE;
        }

        // 3. Scanner récursivement pour trouver les photos
        $photos = $this->findAllPhotos($agencyPath);
        
        if (empty($photos)) {
            $io->error("❌ Aucune photo trouvée dans {$agencyPath}");
            
            // Vérifier s'il y a des répertoires
            $this->showDirectoryStructure($agencyPath, $io);
            
        } else {
            $io->success("✅ {" . count($photos) . "} photos trouvées:");
            
            // Afficher les premières photos
            foreach (array_slice($photos, 0, 10) as $photo) {
                $relativePath = str_replace($basePath, '', $photo);
                $size = number_format(filesize($photo)) . ' octets';
                $io->writeln("  📸 {$relativePath} ({$size})");
            }
            
            if (count($photos) > 10) {
                $io->writeln("  ... et " . (count($photos) - 10) . " autres photos");
            }
        }

        // 4. Analyser un équipement spécifique
        $io->section('🔍 Analyse d\'un équipement spécifique');
        $this->analyzeSpecificEquipment($agency, $io);

        // 5. Tester le service ImageStorageService
        $io->section('🧪 Test du service ImageStorageService');
        $this->testImageStorageService($agency, $io);

        return Command::SUCCESS;
    }

    private function findAllPhotos(string $directory): array
    {
        $photos = [];
        
        if (!is_dir($directory)) {
            return $photos;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'jpg') {
                $photos[] = $file->getPathname();
            }
        }

        return $photos;
    }

    private function showDirectoryStructure(string $directory, SymfonyStyle $io): void
    {
        $io->writeln("📂 Structure du répertoire:");
        
        if (!is_dir($directory)) {
            $io->writeln("  Répertoire inexistant");
            return;
        }

        $items = scandir($directory);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $fullPath = $directory . '/' . $item;
            if (is_dir($fullPath)) {
                $io->writeln("  📁 {$item}/");
                
                // Afficher le contenu du sous-répertoire
                $subItems = scandir($fullPath);
                foreach ($subItems as $subItem) {
                    if ($subItem === '.' || $subItem === '..') continue;
                    $subPath = $fullPath . '/' . $subItem;
                    if (is_dir($subPath)) {
                        $io->writeln("    📁 {$subItem}/");
                        
                        // Un niveau de plus
                        $subSubItems = scandir($subPath);
                        foreach ($subSubItems as $subSubItem) {
                            if ($subSubItem === '.' || $subSubItem === '..') continue;
                            $subSubPath = $subPath . '/' . $subSubItem;
                            if (is_dir($subSubPath)) {
                                $io->writeln("      📁 {$subSubItem}/");
                                
                                // Compter les fichiers
                                $files = glob($subSubPath . '/*.jpg');
                                if (!empty($files)) {
                                    $io->writeln("        📸 " . count($files) . " photos");
                                }
                            } else {
                                $io->writeln("      📄 {$subSubItem}");
                            }
                        }
                    } else {
                        $io->writeln("    📄 {$subItem}");
                    }
                }
            } else {
                $io->writeln("  📄 {$item}");
            }
        }
    }

    private function analyzeSpecificEquipment(string $agency, SymfonyStyle $io): void
    {
        try {
            // Prendre le premier équipement qui a été migré
            $entityClass = "App\\Entity\\Equipement{$agency}";
            $repository = $this->entityManager->getRepository($entityClass);
            
            $equipment = $repository->findOneBy(['numero_equipement' => 'RAP01']);
            
            if (!$equipment) {
                $io->writeln("❌ Équipement RAP01 non trouvé");
                return;
            }

            $io->writeln("📋 Analyse de l'équipement RAP01:");
            $io->definitionList(
                ['Numéro' => $equipment->getNumeroEquipement()],
                ['Raison Sociale' => $equipment->getRaisonSociale()],
                ['Visite' => $equipment->getVisite()],
                ['Date' => $equipment->getDateEnregistrement()],
                ['Code Agence' => $equipment->getCodeAgence()]
            );

            // Calculer le chemin attendu
            $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
            $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
            $typeVisite = $equipment->getVisite();

            $io->writeln("🗂️ Données pour le chemin:");
            $io->definitionList(
                ['Raison Sociale (nettoyée)' => $this->cleanFileName($raisonSociale)],
                ['Année visite' => $anneeVisite],
                ['Type visite' => $typeVisite]
            );

            $expectedPath = $this->imageStorageService->getBaseImagePath() . 
                           $agency . '/' . 
                           $this->cleanFileName($raisonSociale) . '/' . 
                           $anneeVisite . '/' . 
                           $typeVisite;

            $io->writeln("📁 Chemin attendu: {$expectedPath}");
            
            if (is_dir($expectedPath)) {
                $photos = glob($expectedPath . '/*.jpg');
                $io->success("✅ Répertoire trouvé avec " . count($photos) . " photos");
                
                foreach ($photos as $photo) {
                    $filename = basename($photo);
                    $size = number_format(filesize($photo));
                    $io->writeln("  📸 {$filename} ({$size} octets)");
                }
            } else {
                $io->error("❌ Répertoire attendu non trouvé");
                
                // Chercher des répertoires similaires
                $parentPath = dirname($expectedPath);
                if (is_dir($parentPath)) {
                    $io->writeln("🔍 Répertoires dans " . basename($parentPath) . ":");
                    $dirs = scandir($parentPath);
                    foreach ($dirs as $dir) {
                        if ($dir !== '.' && $dir !== '..' && is_dir($parentPath . '/' . $dir)) {
                            $io->writeln("  📁 {$dir}");
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $io->error("❌ Erreur analyse équipement: " . $e->getMessage());
        }
    }

    private function testImageStorageService(string $agency, SymfonyStyle $io): void
    {
        try {
            // Test du service avec des données connues
            $testExists = $this->imageStorageService->imageExists(
                $agency,
                'KLT_STRASBOURG',
                '2025',
                'CE2',
                'RAP01_compte_rendu'
            );

            $io->writeln("🧪 Test existence photo RAP01_compte_rendu: " . ($testExists ? '✅ Trouvée' : '❌ Non trouvée'));

            // Test avec noms alternatifs
            $alternativeNames = [
                'KLT STRASBOURG',
                'KLT_STRASBOURG', 
                'KLT__STRASBOURG',
                'KLT___STRASBOURG'
            ];

            $io->writeln("🔍 Test avec différents noms de client:");
            foreach ($alternativeNames as $name) {
                $exists = $this->imageStorageService->imageExists($agency, $name, '2025', 'CE2', 'RAP01_compte_rendu');
                $io->writeln("  - {$name}: " . ($exists ? '✅' : '❌'));
            }

            // Statistiques générales
            $stats = $this->imageStorageService->getStorageStats();
            $agencyStats = $stats['agencies'][$agency] ?? null;
            
            if ($agencyStats) {
                $io->writeln("📊 Statistiques de l'agence {$agency}:");
                $io->definitionList(
                    ['Photos' => $agencyStats['count']],
                    ['Taille' => $agencyStats['size_formatted']]
                );
            } else {
                $io->writeln("❌ Aucune statistique trouvée pour l'agence {$agency}");
            }

        } catch (\Exception $e) {
            $io->error("❌ Erreur test service: " . $e->getMessage());
        }
    }

    private function cleanFileName(string $name): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name);
        $cleaned = preg_replace('/_+/', '_', $cleaned);
        return trim($cleaned, '_');
    }
}

/**
 * UTILISATION :
 * 
 * php bin/console app:debug-photo-location S140
 * 
 * Cette commande va vous montrer exactement où sont stockées vos photos
 * et pourquoi vous ne les trouvez pas au bon endroit.
 */