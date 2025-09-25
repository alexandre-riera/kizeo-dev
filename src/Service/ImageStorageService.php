<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service de gestion du stockage des images d'équipements
 * Structure: /public/img/AGENCE/RAISON_SOCIALE/ANNEE/TYPE_VISITE/CODE_EQUIPEMENT_TYPE_PHOTO.jpg
 * 
 * Exemples:
 * - /public/img/S140/HEXCEL/2025/CE1/RAP01_compte_rendu.jpg
 * - /public/img/S50/ATOUT_BOX_CASTELNAU/2025/CE2/SEC03_plaque.jpg
 */
class ImageStorageService
{
    private string $baseImagePath;
    private LoggerInterface $logger;

    public function __construct(string $projectDir, LoggerInterface $logger)
    {
        $this->baseImagePath = $projectDir . '/public/img/';
        $this->logger = $logger;
        
        // Créer le répertoire de base si nécessaire
        if (!is_dir($this->baseImagePath)) {
            mkdir($this->baseImagePath, 0755, true);
        }
    }

    /**
     * Stocke une image d'équipement dans le système de fichiers
     * 
     * @param string $agence Code agence (S140, S50, etc.)
     * @param string $raisonSociale Nom du client
     * @param string $annee Année de la visite
     * @param string $typeVisite Type de visite (CE1, CE2, etc.)
     * @param string $filename Nom du fichier (avec extension)
     * @param string $imageContent Contenu binaire de l'image
     * @return string Chemin complet du fichier sauvegardé
     * @throws \RuntimeException Si la sauvegarde échoue
     */

    /**
    * MÉTHODE MANQUANTE - Retourne le chemin de base des images
    */
    public function getBaseImagePath(): string
    {
        return $this->baseImagePath;
    }

    public function storeImage(
        string $agence, 
        string $raisonSociale, 
        string $annee, 
        string $typeVisite, 
        string $filename, 
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
        
        // Ajouter l'extension .jpg si elle n'est pas présente
        if (!str_ends_with(strtolower($filename), '.jpg')) {
            $filename .= '.jpg';
        }
        
        $filepath = $directory . '/' . $filename;
        
        // Sauvegarde de l'image avec verrouillage exclusif
        if (file_put_contents($filepath, $imageContent, LOCK_EX) === false) {
            throw new \RuntimeException("Impossible d'écrire le fichier: {$filepath}");
        }
        
        // Vérifier que le fichier a bien été créé et n'est pas vide
        if (!file_exists($filepath) || filesize($filepath) === 0) {
            throw new \RuntimeException("Le fichier créé est vide ou inexistant: {$filepath}");
        }
        
        $this->logger->info("Image sauvegardée: {$filepath} (" . number_format(strlen($imageContent)) . " octets)");
        
        return $filepath;
    }

    /**
     * Récupère le chemin absolu d'une image si elle existe
     */
    public function getImagePath(
        string $agence, 
        string $raisonSociale, 
        string $annee, 
        string $typeVisite, 
        string $filename
    ): ?string {
        $cleanRaisonSociale = $this->cleanFileName($raisonSociale);
        $cleanFilename = $this->cleanFileName($filename);
        
        // Ajouter l'extension .jpg si elle n'est pas présente
        if (!str_ends_with(strtolower($cleanFilename), '.jpg')) {
            $cleanFilename .= '.jpg';
        }
        
        $filepath = $this->buildDirectoryPath($agence, $cleanRaisonSociale, $annee, $typeVisite) 
                   . '/' . $cleanFilename;
        
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
        string $filename
    ): ?string {
        $imagePath = $this->getImagePath($agence, $raisonSociale, $annee, $typeVisite, $filename);
        
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
        string $filename
    ): bool {
        return $this->getImagePath($agence, $raisonSociale, $annee, $typeVisite, $filename) !== null;
    }

    /**
     * Supprime une image et retourne true si la suppression a réussi
     */
    public function deleteImage(
        string $agence, 
        string $raisonSociale, 
        string $annee, 
        string $typeVisite, 
        string $filename
    ): bool {
        $imagePath = $this->getImagePath($agence, $raisonSociale, $annee, $typeVisite, $filename);
        
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
     * Récupère toutes les images d'un équipement
     */
    public function getAllImagesForEquipment(
        string $agence,
        string $raisonSociale,
        string $annee,
        string $typeVisite,
        string $codeEquipement
    ): array {
        $images = [];
        $directory = $this->buildDirectoryPath($agence, $this->cleanFileName($raisonSociale), $annee, $typeVisite);
        
        if (!is_dir($directory)) {
            return $images;
        }
        
        $files = scandir($directory);
        $pattern = '/^' . preg_quote($this->cleanFileName($codeEquipement), '/') . '_.*\.jpg$/i';
        
        foreach ($files as $file) {
            if (preg_match($pattern, $file)) {
                $fullPath = $directory . '/' . $file;
                $photoType = $this->extractPhotoTypeFromFilename($file, $codeEquipement);
                
                $images[$photoType] = [
                    'filename' => $file,
                    'path' => $fullPath,
                    'url' => $this->getImageUrl($agence, $raisonSociale, $annee, $typeVisite, pathinfo($file, PATHINFO_FILENAME)),
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath)
                ];
            }
        }
        
        return $images;
    }

    /**
     * Nettoie les répertoires vides pour une agence
     */
    public function cleanEmptyDirectories(string $agence = ''): int
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
            'total_size_formatted' => '0 B',
            'agencies' => [],
            'last_scan' => date('Y-m-d H:i:s')
        ];

        if (!is_dir($this->baseImagePath)) {
            return $stats;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->baseImagePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'jpg') {
                $stats['total_images']++;
                $fileSize = $file->getSize();
                $stats['total_size'] += $fileSize;
                
                // Extraire l'agence du chemin
                $pathParts = explode(DIRECTORY_SEPARATOR, $file->getPath());
                $basePathParts = explode(DIRECTORY_SEPARATOR, $this->baseImagePath);
                $relativeParts = array_slice($pathParts, count($basePathParts));
                
                if (!empty($relativeParts)) {
                    $agence = $relativeParts[0];
                    if (!isset($stats['agencies'][$agence])) {
                        $stats['agencies'][$agence] = [
                            'count' => 0,
                            'size' => 0
                        ];
                    }
                    $stats['agencies'][$agence]['count']++;
                    $stats['agencies'][$agence]['size'] += $fileSize;
                }
            }
        }

        // Formater la taille totale
        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);
        
        // Formater les tailles par agence
        foreach ($stats['agencies'] as $agence => &$agencyStats) {
            $agencyStats['size_formatted'] = $this->formatBytes($agencyStats['size']);
        }

        return $stats;
    }

    /**
     * Sauvegarde par lot (batch) pour optimiser les performances
     */
    public function storeBatchImages(array $imagesBatch): array
    {
        $results = [
            'success' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        foreach ($imagesBatch as $imageData) {
            try {
                $this->storeImage(
                    $imageData['agence'],
                    $imageData['raison_sociale'],
                    $imageData['annee'],
                    $imageData['type_visite'],
                    $imageData['filename'],
                    $imageData['content']
                );
                $results['success']++;
            } catch (\Exception $e) {
                $results['errors']++;
                $results['error_details'][] = [
                    'filename' => $imageData['filename'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                $this->logger->error("Erreur sauvegarde batch: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Migration des anciennes photos vers la nouvelle structure
     */
    public function migratePhotosFromForm(array $formEntities): array
    {
        $migrationResults = [
            'migrated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        foreach ($formEntities as $form) {
            try {
                // Extraire les informations nécessaires
                $equipmentInfo = $this->extractEquipmentInfoFromForm($form);
                
                if (!$equipmentInfo) {
                    $migrationResults['skipped']++;
                    continue;
                }

                // Migrer chaque photo de l'entité Form
                $photoFields = $this->getFormPhotoFields();
                
                foreach ($photoFields as $getter => $photoType) {
                    if (method_exists($form, $getter)) {
                        $photoName = $form->$getter();
                        
                        if (!empty($photoName)) {
                            // Télécharger et sauvegarder la photo
                            $this->migratePhotoFromKizeo($form, $photoName, $equipmentInfo, $photoType);
                        }
                    }
                }
                
                $migrationResults['migrated']++;
                
            } catch (\Exception $e) {
                $migrationResults['errors']++;
                $this->logger->error("Erreur migration photo: " . $e->getMessage());
            }
        }

        return $migrationResults;
    }

    /**
     * Construit le chemin du répertoire
     */
    private function buildDirectoryPath(string $agence, string $raisonSociale, string $annee, string $typeVisite): string
    {
        return $this->baseImagePath . $agence . '/' . $raisonSociale . '/' . $annee . '/' . $typeVisite;
    }

    /**
     * Nettoie un nom de fichier/répertoire en remplaçant les caractères spéciaux
     */
    private function cleanFileName(string $name): string
    {
        // Remplace les caractères spéciaux et espaces par des underscores
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
                    if (rmdir($itemPath)) {
                        $removed++;
                    }
                } else {
                    $isEmpty = false;
                }
            } else {
                $isEmpty = false;
            }
        }

        return $removed;
    }

    /**
     * Formate les octets en unités lisibles
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        
        $value = $bytes / pow(1024, $power);
        
        return round($value, 2) . ' ' . $units[$power];
    }

    /**
     * Extrait le type de photo à partir du nom de fichier
     */
    private function extractPhotoTypeFromFilename(string $filename, string $codeEquipement): string
    {
        $cleanCode = $this->cleanFileName($codeEquipement);
        $pattern = '/^' . preg_quote($cleanCode, '/') . '_(.+)\.jpg$/i';
        
        if (preg_match($pattern, $filename, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }

    /**
     * Extrait les informations d'équipement depuis une entité Form
     */
    private function extractEquipmentInfoFromForm($form): ?array
    {
        // Cette méthode doit être adaptée selon la structure de votre entité Form
        try {
            return [
                'agence' => $this->extractAgenceFromRaisonSociale($form->getRaisonSocialeVisite()),
                'raison_sociale' => explode('\\', $form->getRaisonSocialeVisite())[0] ?? '',
                'annee' => date('Y', strtotime($form->getUpdateTime())),
                'type_visite' => explode('\\', $form->getRaisonSocialeVisite())[1] ?? 'CE1',
                'code_equipement' => $form->getCodeEquipement()
            ];
        } catch (\Exception $e) {
            $this->logger->error("Impossible d'extraire les infos équipement: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrait le code agence depuis la raison sociale visite
     */
    private function extractAgenceFromRaisonSociale(string $raisonSocialeVisite): string
    {
        // Logique pour extraire l'agence - à adapter selon vos données
        // Par exemple, si l'agence est stockée ailleurs ou peut être déduite
        return 'S140'; // Valeur par défaut - à modifier
    }

    /**
     * Mapping des getters de Form vers les types de photos
     */
    private function getFormPhotoFields(): array
    {
        return [
            'getPhotoCompteRendu' => 'compte_rendu',
            'getPhotoEnvironnementEquipement1' => 'environnement',
            'getPhotoPlaque' => 'plaque',
            'getPhotoEtiquetteSomafi' => 'etiquette_somafi',
            'getPhotoMoteur' => 'moteur',
            'getPhoto2' => 'generale'
        ];
    }

    /**
     * Migre une photo depuis Kizeo vers le stockage local
     */
    private function migratePhotoFromKizeo($form, string $photoName, array $equipmentInfo, string $photoType): void
    {
        // Cette méthode nécessiterait l'injection du HttpClient pour télécharger depuis Kizeo
        // Elle est laissée en stub pour l'exemple
        $this->logger->info("Migration photo {$photoName} pour équipement {$equipmentInfo['code_equipement']}");
    }

    /**
     * Vérifie l'intégrité d'une image
     */
    public function verifyImageIntegrity(string $imagePath): bool
    {
        if (!file_exists($imagePath)) {
            return false;
        }

        // Vérifier que c'est bien une image JPEG valide
        $imageInfo = @getimagesize($imagePath);
        return $imageInfo !== false && $imageInfo[2] === IMAGETYPE_JPEG;
    }

    /**
     * Obtient les métadonnées d'une image
     */
    public function getImageMetadata(string $imagePath): ?array
    {
        if (!file_exists($imagePath)) {
            return null;
        }

        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return null;
        }

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => $imageInfo[2],
            'mime' => $imageInfo['mime'],
            'size' => filesize($imagePath),
            'created' => filectime($imagePath),
            'modified' => filemtime($imagePath)
        ];
    }

    /**
     * Récupère l'URL de l'image générale d'un équipement
     */
    public function getGeneralImageUrl(
        string $agence, 
        string $raisonSociale, 
        string $annee, 
        string $typeVisite, 
        string $codeEquipement
    ): ?string {
        // Chercher l'image avec le suffixe _generale
        $generalImagePath = $this->getImagePath(
            $agence,
            $raisonSociale,
            $annee,
            $typeVisite,
            $codeEquipement . '_generale'
        );
        
        if ($generalImagePath) {
            // Convertir le chemin absolu en URL relative
            $relativePath = str_replace($this->baseImagePath, '/img/', $generalImagePath);
            return $relativePath;
        }
        
        return null;
    }

    /**
     * Récupère l'image générale en base64 pour PDF
     */
    public function getGeneralImageBase64(
        string $agence, 
        string $raisonSociale, 
        string $annee, 
        string $typeVisite, 
        string $codeEquipement
    ): ?string {
        $generalImagePath = $this->getImagePath(
            $agence,
            $raisonSociale,
            $annee,
            $typeVisite,
            $codeEquipement . '_generale'
        );
        
        if ($generalImagePath && file_exists($generalImagePath)) {
            return base64_encode(file_get_contents($generalImagePath));
        }
        
        return null;
    }

}