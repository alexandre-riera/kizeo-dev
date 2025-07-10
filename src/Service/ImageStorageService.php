<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service de gestion du stockage des images d'équipements
 * Structure: /public/img/AGENCE/RAISON_SOCIALE/ANNEE/TYPE_VISITE/CODE_EQUIPEMENT.jpg
 */
class ImageStorageService
{
    private string $baseImagePath;
    private LoggerInterface $logger;

    public function __construct(string $projectDir, LoggerInterface $logger)
    {
        $this->baseImagePath = $projectDir . '/public/img/';
        $this->logger = $logger;
    }

    /**
     * Stocke une image d'équipement dans le système de fichiers
     */
    public function storeImage(
        string $agence, 
        string $raisonSociale, 
        string $annee, 
        string $typeVisite, 
        string $codeEquipement, 
        string $imageContent
    ): string {
        $cleanRaisonSociale = $this->cleanFileName($raisonSociale);
        $directory = $this->buildDirectoryPath($agence, $cleanRaisonSociale, $annee, $typeVisite);
        
        // Création du répertoire si inexistant
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new \RuntimeException("Impossible de créer le répertoire: {$directory}");
            }
        }
        
        $filename = $this->cleanFileName($codeEquipement) . '.jpg';
        $filepath = $directory . '/' . $filename;
        
        // Sauvegarde de l'image
        if (file_put_contents($filepath, $imageContent, LOCK_EX) === false) {
            throw new \RuntimeException("Impossible d'écrire le fichier: {$filepath}");
        }
        
        $this->logger->info("Image sauvegardée: {$filepath}");
        
        return $filepath;
    }

    /**
     * Récupère le chemin d'une image si elle existe
     */
    public function getImagePath(
        string $agence, 
        string $raisonSociale, 
        string $annee, 
        string $typeVisite, 
        string $codeEquipement
    ): ?string {
        $cleanRaisonSociale = $this->cleanFileName($raisonSociale);
        $cleanCodeEquipement = $this->cleanFileName($codeEquipement);
        $filepath = $this->buildDirectoryPath($agence, $cleanRaisonSociale, $annee, $typeVisite) 
                   . '/' . $cleanCodeEquipement . '.jpg';
        
        return file_exists($filepath) ? $filepath : null;
    }

    /**
     * Récupère l'URL publique d'une image pour l'affichage web
     */
    public function getImageUrl(
        string $agence, 
        string $raisonSociale, 
        string $annee, 
        string $typeVisite, 
        string $codeEquipement
    ): ?string {
        $imagePath = $this->getImagePath($agence, $raisonSociale, $annee, $typeVisite, $codeEquipement);
        
        if (!$imagePath) {
            return null;
        }
        
        // Convertir le chemin absolu en URL relative
        $relativePath = str_replace($this->baseImagePath, '/img/', $imagePath);
        return $relativePath;
    }

    /**
     * Vérifie si une image existe
     */
    public function imageExists(
        string $agence, 
        string $raisonSociale, 
        string $annee, 
        string $typeVisite, 
        string $codeEquipement
    ): bool {
        return $this->getImagePath($agence, $raisonSociale, $annee, $typeVisite, $codeEquipement) !== null;
    }

    /**
     * Supprime une image
     */
    public function deleteImage(
        string $agence, 
        string $raisonSociale, 
        string $annee, 
        string $typeVisite, 
        string $codeEquipement
    ): bool {
        $imagePath = $this->getImagePath($agence, $raisonSociale, $annee, $typeVisite, $codeEquipement);
        
        if ($imagePath && file_exists($imagePath)) {
            $deleted = unlink($imagePath);
            if ($deleted) {
                $this->logger->info("Image supprimée: {$imagePath}");
            }
            return $deleted;
        }
        
        return false;
    }

    /**
     * Nettoie les anciens répertoires vides
     */
    public function cleanEmptyDirectories(string $agence): int
    {
        $basePath = $agence ? $this->baseImagePath . $agence : $this->baseImagePath;
        return $this->removeEmptyDirectories($basePath);
    }

    /**
     * Récupère les statistiques de stockage
     */
    public function getStorageStats(): array
    {
        $stats = [
            'total_images' => 0,
            'total_size' => 0,
            'agencies' => []
        ];

        if (!is_dir($this->baseImagePath)) {
            return $stats;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->baseImagePath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'jpg') {
                $stats['total_images']++;
                $stats['total_size'] += $file->getSize();
                
                // Extraire l'agence du chemin
                $path = $file->getPath();
                $agence = basename(dirname(dirname(dirname($path))));
                if (!isset($stats['agencies'][$agence])) {
                    $stats['agencies'][$agence] = 0;
                }
                $stats['agencies'][$agence]++;
            }
        }

        return $stats;
    }

    /**
     * Construit le chemin du répertoire
     */
    private function buildDirectoryPath(string $agence, string $raisonSociale, string $annee, string $typeVisite): string
    {
        return $this->baseImagePath . $agence . '/' . $raisonSociale . '/' . $annee . '/' . $typeVisite;
    }

    /**
     * Nettoie un nom de fichier/répertoire
     */
    private function cleanFileName(string $name): string
    {
        // Remplace les caractères spéciaux par des underscores
        $cleaned = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name);
        // Supprime les underscores multiples
        $cleaned = preg_replace('/_+/', '_', $cleaned);
        // Supprime les underscores en début/fin
        return trim($cleaned, '_');
    }

    /**
     * Supprime récursivement les répertoires vides
     */
    private function removeEmptyDirectories(string $path): int
    {
        $removed = 0;
        
        if (!is_dir($path)) {
            return $removed;
        }

        $items = scandir($path);
        $isEmpty = true;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            
            if (is_dir($itemPath)) {
                $removed += $this->removeEmptyDirectories($itemPath);
                // Revérifier si le répertoire est maintenant vide
                if (count(scandir($itemPath)) <= 2) {
                    rmdir($itemPath);
                    $removed++;
                } else {
                    $isEmpty = false;
                }
            } else {
                $isEmpty = false;
            }
        }

        return $removed;
    }
}