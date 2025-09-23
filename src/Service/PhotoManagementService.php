<?php
// src/Service/PhotoManagementService.php

namespace App\Service;

use App\Entity\Form;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class PhotoManagementService
{
    private ImageStorageService $imageStorageService;
    private HttpClientInterface $client;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ImageStorageService $imageStorageService,
        HttpClientInterface $client,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager
    ) {
        $this->imageStorageService = $imageStorageService;
        $this->client = $client;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
    }

    /**
     * Récupère les photos d'un équipement (priorité aux photos locales, fallback API)
     */
    public function getEquipmentPhotos($equipment): array
    {
        $picturesdata = [];
        
        try {
            // 1. Essayer de récupérer les photos locales
            $localPhotos = $this->getLocalPhotos($equipment);
            
            if (!empty($localPhotos)) {
                return $localPhotos;
            }
            
            // 2. Fallback : récupérer depuis l'API et sauvegarder localement
            $this->logger->info("Photos locales introuvables pour {$equipment->getNumeroEquipement()}, téléchargement depuis API");
            
            return $this->downloadAndStorePhotos($equipment);
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur récupération photos équipement {$equipment->getNumeroEquipement()}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les photos depuis le stockage local
     */
    private function getLocalPhotos($equipment): array
    {
        $picturesdata = [];
        $agence = $equipment->getAgence() ?: $equipment->getRaisonSociale();
        $raisonSociale = $this->cleanString($equipment->getRaisonSociale());
        $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
        $typeVisite = $equipment->getVisite();
        $codeEquipement = $equipment->getNumeroEquipement();
        
        // Types de photos à rechercher (par ordre de priorité)
        $photoTypes = [
            'generale',      // photo_2 -> {numero_equipement}_generale.jpg
            'compte_rendu'   // photo_compte_rendu -> {numero_equipement}_compte_rendu.jpg
        ];
        
        foreach ($photoTypes as $photoType) {
            $filename = $codeEquipement . '_' . $photoType;
            $imagePath = $this->imageStorageService->getImagePath(
                $agence,
                $raisonSociale,
                $anneeVisite,
                $typeVisite,
                $filename
            );
            
            if ($imagePath && file_exists($imagePath)) {
                $pictureEncoded = base64_encode(file_get_contents($imagePath));
                
                $picturesdataObject = new \stdClass();
                $picturesdataObject->picture = $pictureEncoded;
                $picturesdataObject->update_time = date('Y-m-d H:i:s', filemtime($imagePath));
                $picturesdataObject->photo_type = $photoType;
                
                $picturesdata[] = $picturesdataObject;
            }
        }
        
        return $picturesdata;
    }

    /**
     * Télécharge les photos depuis l'API Kizeo et les sauvegarde localement
     */
    private function downloadAndStorePhotos($equipment): array
    {
        $picturesdata = [];
        
        // Récupérer les données du formulaire depuis la base
        $formsData = $this->entityManager->getRepository(Form::class)->findBy([
            'code_equipement' => $equipment->getNumeroEquipement(),
            'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()
        ]);
        
        if (empty($formsData)) {
            $this->logger->warning("Aucune donnée de formulaire trouvée pour {$equipment->getNumeroEquipement()}");
            return [];
        }
        
        $formData = $formsData[0]; // Prendre le premier
        
        // Mapping des champs photos vers les noms de fichiers locaux
        $photoMapping = [
            'photo_2' => 'generale',
            'photo_compte_rendu' => 'compte_rendu'
        ];
        
        $agence = $equipment->getAgence() ?: $equipment->getRaisonSociale();
        $raisonSociale = $this->cleanString($equipment->getRaisonSociale());
        $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
        $typeVisite = $equipment->getVisite();
        $codeEquipement = $equipment->getNumeroEquipement();
        
        foreach ($photoMapping as $photoField => $photoType) {
            $photoName = $this->getPhotoFieldValue($formData, $photoField);
            
            if (empty($photoName)) {
                continue;
            }
            
            try {
                // Gérer les photos multiples séparées par des virgules
                $photoNames = strpos($photoName, ', ') !== false ? 
                    explode(', ', $photoName) : [$photoName];
                
                foreach ($photoNames as $index => $singlePhotoName) {
                    $singlePhotoName = trim($singlePhotoName);
                    if (empty($singlePhotoName)) continue;

                    $filename = count($photoNames) > 1 
                        ? $codeEquipement . '_' . $photoType . '_' . ($index + 1)
                        : $codeEquipement . '_' . $photoType;

                    // Télécharger et sauvegarder
                    $imageContent = $this->downloadFromKizeoApi(
                        $formData->getFormId(), 
                        $formData->getDataId(), 
                        $singlePhotoName
                    );
                    
                    if ($imageContent) {
                        // Sauvegarder localement
                        $savedPath = $this->imageStorageService->storeImage(
                            $agence,
                            $raisonSociale,
                            $anneeVisite,
                            $typeVisite,
                            $filename,
                            $imageContent
                        );
                        
                        // Ajouter à la liste des photos pour le PDF
                        $pictureEncoded = base64_encode($imageContent);
                        
                        $picturesdataObject = new \stdClass();
                        $picturesdataObject->picture = $pictureEncoded;
                        $picturesdataObject->update_time = date('Y-m-d H:i:s');
                        $picturesdataObject->photo_type = $photoType;
                        
                        $picturesdata[] = $picturesdataObject;
                        
                        $this->logger->info("Photo téléchargée et sauvegardée: {$savedPath}");
                    }
                }
                
            } catch (\Exception $e) {
                $this->logger->error("Erreur téléchargement photo {$photoField}: " . $e->getMessage());
            }
        }
        
        return $picturesdata;
    }

    /**
     * Télécharge une photo depuis l'API Kizeo
     */
    private function downloadFromKizeoApi(string $formId, string $dataId, string $photoName): ?string
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

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $imageContent = $response->getContent();
            return !empty($imageContent) ? $imageContent : null;
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur API Kizeo pour {$photoName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère la valeur d'un champ photo depuis l'entité Form
     */
    private function getPhotoFieldValue(Form $formData, string $fieldName): ?string
    {
        switch ($fieldName) {
            case 'photo_2':
                return $formData->getPhoto2();
            case 'photo_compte_rendu':
                return $formData->getPhotoCompteRendu();
            default:
                return null;
        }
    }

    /**
     * Nettoie une chaîne pour les noms de fichiers/dossiers
     */
    private function cleanString(string $string): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $string);
    }

    /**
     * Télécharge toutes les photos manquantes pour un client
     */
    public function downloadMissingPhotosForClient(string $clientName): array
    {
        $results = [
            'downloaded' => 0,
            'errors' => 0,
            'skipped' => 0
        ];
        
        try {
            // Récupérer tous les formulaires du client
            $formsData = $this->entityManager->getRepository(Form::class)
                ->createQueryBuilder('f')
                ->where('f.raison_sociale_visite LIKE :client')
                ->setParameter('client', $clientName . '%')
                ->getQuery()
                ->getResult();
            
            foreach ($formsData as $formData) {
                $result = $this->downloadPhotosForForm($formData);
                $results['downloaded'] += $result['downloaded'];
                $results['errors'] += $result['errors'];
                $results['skipped'] += $result['skipped'];
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur téléchargement photos client {$clientName}: " . $e->getMessage());
            $results['errors']++;
        }
        
        return $results;
    }

    /**
     * Télécharge les photos pour un formulaire spécifique
     */
    private function downloadPhotosForForm(Form $formData): array
    {
        $results = ['downloaded' => 0, 'errors' => 0, 'skipped' => 0];
        
        // Extraire les informations du client/visite
        $raisonSocialeVisite = $formData->getRaisonSocialeVisite();
        [$raisonSociale, $typeVisite] = explode('\\', $raisonSocialeVisite);
        
        $agence = $raisonSociale; // ou une autre logique pour déterminer l'agence
        $raisonSocialeClean = $this->cleanString($raisonSociale);
        $anneeVisite = date('Y', strtotime($formData->getUpdateTime()));
        $codeEquipement = $formData->getCodeEquipement();
        
        $photoMapping = [
            'photo_2' => 'generale',
            'photo_compte_rendu' => 'compte_rendu'
        ];
        
        foreach ($photoMapping as $photoField => $photoType) {
            $photoName = $this->getPhotoFieldValue($formData, $photoField);
            
            if (empty($photoName)) {
                $results['skipped']++;
                continue;
            }
            
            $filename = $codeEquipement . '_' . $photoType;
            
            // Vérifier si la photo existe déjà localement
            if ($this->imageStorageService->imageExists($agence, $raisonSocialeClean, $anneeVisite, $typeVisite, $filename)) {
                $results['skipped']++;
                continue;
            }
            
            // Télécharger depuis l'API
            $imageContent = $this->downloadFromKizeoApi(
                $formData->getFormId(),
                $formData->getDataId(),
                trim($photoName)
            );
            
            if ($imageContent) {
                try {
                    $this->imageStorageService->storeImage(
                        $agence,
                        $raisonSocialeClean,
                        $anneeVisite,
                        $typeVisite,
                        $filename,
                        $imageContent
                    );
                    $results['downloaded']++;
                } catch (\Exception $e) {
                    $this->logger->error("Erreur sauvegarde {$filename}: " . $e->getMessage());
                    $results['errors']++;
                }
            } else {
                $results['errors']++;
            }
        }
        
        return $results;
    }
}