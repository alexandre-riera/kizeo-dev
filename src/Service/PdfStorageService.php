<?php

// ===== 1. SERVICE DE STOCKAGE PDF =====

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service de gestion du stockage des PDFs clients
 * Structure: /storage/pdf/AGENCE/CLIENT/ANNEE/VISITE/client_annee_visite.pdf
 */
class PdfStorageService
{
    private string $basePdfPath;
    private LoggerInterface $logger;

    public function __construct(string $projectDir, LoggerInterface $logger)
    {
        $this->basePdfPath = $projectDir . '/storage/pdf/';
        $this->logger = $logger;
        
        // Créer le répertoire de base s'il n'existe pas
        if (!is_dir($this->basePdfPath)) {
            mkdir($this->basePdfPath, 0755, true);
        }
    }

    /**
     * Stocke un PDF client dans le système de fichiers
     */
    public function storePdf(
        string $agence, 
        string $clientId, 
        string $annee, 
        string $visite, 
        string $pdfContent,
        ?string $customFilename = null
    ): string {
        $directory = $this->buildDirectoryPath($agence, $clientId, $annee, $visite);
        
        // Création du répertoire si inexistant
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new \RuntimeException("Impossible de créer le répertoire: {$directory}");
            }
        }
        
        $filename = $customFilename ?? "client_{$clientId}_{$annee}_{$visite}.pdf";
        $filepath = $directory . '/' . $filename;
        
        // Sauvegarde du PDF
        if (file_put_contents($filepath, $pdfContent, LOCK_EX) === false) {
            throw new \RuntimeException("Impossible d'écrire le fichier: {$filepath}");
        }
        
        $this->logger->info("PDF sauvegardé: {$filepath}");
        
        return $filepath;
    }

    /**
     * Récupère le chemin d'un PDF s'il existe
     */
    public function getPdfPath(
        string $agence, 
        string $clientId, 
        string $annee, 
        string $visite,
        ?string $customFilename = null
    ): ?string {
        $filename = $customFilename ?? "client_{$clientId}_{$annee}_{$visite}.pdf";
        $filepath = $this->buildDirectoryPath($agence, $clientId, $annee, $visite) . '/' . $filename;
        
        return file_exists($filepath) ? $filepath : null;
    }

    /**
     * Construit le chemin du répertoire
     */
    private function buildDirectoryPath(string $agence, string $clientId, string $annee, string $visite): string
    {
        return $this->basePdfPath . $agence . '/' . $this->cleanFileName($clientId) . '/' . $annee . '/' . $this->cleanFileName($visite);
    }

    /**
     * Nettoie un nom de fichier/répertoire
     */
    private function cleanFileName(string $name): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name);
        $cleaned = preg_replace('/_+/', '_', $cleaned);
        return trim($cleaned, '_');
    }
}