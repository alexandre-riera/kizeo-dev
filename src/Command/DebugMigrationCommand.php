<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\FormRepository;
use App\Service\ImageStorageService;

#[AsCommand(
    name: 'app:debug-migration',
    description: 'Debug détaillé de la migration d\'un équipement'
)]
class DebugMigrationCommand extends Command
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
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence')
            ->addArgument('equipment_id', InputArgument::REQUIRED, 'ID de l\'équipement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agency = $input->getArgument('agency');
        $equipmentId = $input->getArgument('equipment_id');

        $io->title("🔍 Debug migration détaillé pour {$equipmentId}");

        try {
            // 1. Récupérer l'équipement
            $io->section('1️⃣ Récupération de l\'équipement');
            $entityClass = "App\\Entity\\Equipement{$agency}";
            $repository = $this->entityManager->getRepository($entityClass);
            $equipment = $repository->findOneBy(['numero_equipement' => $equipmentId]);

            if (!$equipment) {
                $io->error("❌ Équipement {$equipmentId} non trouvé");
                return Command::FAILURE;
            }

            $io->success("✅ Équipement trouvé");
            $io->definitionList(
                ['Numéro' => $equipment->getNumeroEquipement()],
                ['Raison Sociale' => $equipment->getRaisonSociale()],
                ['Visite' => $equipment->getVisite()],
                ['Date' => $equipment->getDateEnregistrement()]
            );

            // 2. Récupérer les données Form
            $io->section('2️⃣ Récupération des données Form');
            $formData = $this->formRepository->findOneBy([
                'equipment_id' => $equipmentId
            ]);

            if (!$formData) {
                $io->error("❌ Données Form non trouvées");
                return Command::FAILURE;
            }

            $io->success("✅ Données Form trouvées");
            $io->definitionList(
                ['Form ID' => $formData->getFormId()],
                ['Data ID' => $formData->getDataId()],
                ['Equipment ID' => $formData->getEquipmentId()],
                ['Raison Sociale Visite' => $formData->getRaisonSocialeVisite()]
            );

            // 3. Inventorier les photos disponibles
            $io->section('3️⃣ Inventaire des photos disponibles');
            $availablePhotos = $this->getAvailablePhotos($formData);

            if (empty($availablePhotos)) {
                $io->error("❌ Aucune photo disponible");
                return Command::FAILURE;
            }

            $io->success("✅ " . count($availablePhotos) . " photos disponibles:");
            foreach ($availablePhotos as $type => $photoName) {
                $io->writeln("  📸 {$type}: {$photoName}");
            }

            // 4. Calculer les chemins de destination
            $io->section('4️⃣ Calcul des chemins de destination');
            $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
            $raisonSocialeClean = $this->cleanFileName($raisonSociale);
            $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
            $typeVisite = $equipment->getVisite();

            $io->definitionList(
                ['Raison Sociale originale' => $raisonSociale],
                ['Raison Sociale nettoyée' => $raisonSocialeClean],
                ['Année visite' => $anneeVisite],
                ['Type visite' => $typeVisite]
            );

            $basePath = $this->imageStorageService->getBaseImagePath();
            $targetDir = "{$basePath}{$agency}/{$raisonSocialeClean}/{$anneeVisite}/{$typeVisite}";
            $io->writeln("📁 Répertoire cible: {$targetDir}");

            // 5. Test de création du répertoire
            $io->section('5️⃣ Test de création du répertoire');
            if (!is_dir($targetDir)) {
                $io->writeln("📁 Répertoire n'existe pas, tentative de création...");
                if (mkdir($targetDir, 0755, true)) {
                    $io->success("✅ Répertoire créé avec succès");
                } else {
                    $io->error("❌ Impossible de créer le répertoire");
                    return Command::FAILURE;
                }
            } else {
                $io->success("✅ Répertoire existe déjà");
            }

            // Vérifier les permissions
            if (!is_writable($targetDir)) {
                $io->error("❌ Répertoire non accessible en écriture");
                return Command::FAILURE;
            }
            $io->success("✅ Répertoire accessible en écriture");

            // 6. Test de téléchargement d'une photo
            $io->section('6️⃣ Test de téléchargement d\'une photo');
            $firstPhotoType = array_key_first($availablePhotos);
            $firstPhotoName = $availablePhotos[$firstPhotoType];

            $io->writeln("📸 Test avec: {$firstPhotoType} ({$firstPhotoName})");

            // Test de l'appel API
            $io->writeln("🌐 Test de l'appel API Kizeo...");
            $apiUrl = "https://forms.kizeo.com/rest/v3/forms/{$formData->getFormId()}/data/{$formData->getDataId()}/medias/{$firstPhotoName}";
            $io->writeln("🔗 URL: {$apiUrl}");

            try {
                $response = $this->client->request(
                    'GET',
                    $apiUrl,
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 30
                    ]
                );

                $statusCode = $response->getStatusCode();
                $io->writeln("📊 Status Code: {$statusCode}");

                if ($statusCode === 200) {
                    $imageContent = $response->getContent();
                    $imageSize = strlen($imageContent);
                    $io->success("✅ Photo téléchargée: {$imageSize} octets");

                    // Test de sauvegarde
                    $io->writeln("💾 Test de sauvegarde...");
                    $filename = $equipmentId . '_' . $firstPhotoType . '.jpg';
                    $fullPath = $targetDir . '/' . $filename;

                    $io->writeln("📁 Chemin complet: {$fullPath}");

                    // Test avec file_put_contents direct
                    if (file_put_contents($fullPath, $imageContent, LOCK_EX) !== false) {
                        $io->success("✅ Photo sauvegardée avec file_put_contents");
                        
                        // Vérifier la taille
                        $savedSize = filesize($fullPath);
                        $io->writeln("📊 Taille sauvegardée: {$savedSize} octets");
                        
                        if ($savedSize === $imageSize) {
                            $io->success("✅ Taille cohérente");
                        } else {
                            $io->error("❌ Taille incohérente");
                        }

                        // Test avec ImageStorageService
                        $io->writeln("🧪 Test avec ImageStorageService...");
                        try {
                            $servicePath = $this->imageStorageService->storeImage(
                                $agency,
                                $raisonSocialeClean,
                                $anneeVisite,
                                $typeVisite,
                                $equipmentId . '_' . $firstPhotoType . '_service',
                                $imageContent
                            );
                            $io->success("✅ ImageStorageService fonctionne: {$servicePath}");
                        } catch (\Exception $e) {
                            $io->error("❌ Erreur ImageStorageService: " . $e->getMessage());
                        }

                    } else {
                        $io->error("❌ Impossible de sauvegarder avec file_put_contents");
                        
                        // Diagnostic des permissions
                        $io->writeln("🔍 Diagnostic des permissions:");
                        $io->writeln("  - Répertoire parent existe: " . (is_dir(dirname($fullPath)) ? 'Oui' : 'Non'));
                        $io->writeln("  - Répertoire parent accessible: " . (is_writable(dirname($fullPath)) ? 'Oui' : 'Non'));
                        $io->writeln("  - Espace disque: " . disk_free_space(dirname($fullPath)) . " octets");
                    }

                } else {
                    $io->error("❌ Erreur API: Status {$statusCode}");
                    $responseBody = $response->getContent(false);
                    $io->writeln("Response: " . substr($responseBody, 0, 200));
                }

            } catch (\Exception $e) {
                $io->error("❌ Erreur appel API: " . $e->getMessage());
            }

            // 7. Résumé et recommandations
            $io->section('7️⃣ Résumé et recommandations');
            $io->listing([
                "Vérifiez les logs d'erreurs du serveur web",
                "Vérifiez les permissions du répertoire public/img/",
                "Vérifiez l'espace disque disponible",
                "Testez la création manuelle d'un fichier dans le répertoire cible"
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("❌ Erreur générale: " . $e->getMessage());
            $io->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function getAvailablePhotos($formData): array
    {
        $photos = [];

        $photoMappings = [
            'compte_rendu' => $formData->getPhotoCompteRendu(),
            'environnement' => $formData->getPhotoEnvironnementEquipement1(),
            'plaque' => $formData->getPhotoPlaque(),
            'etiquette_somafi' => $formData->getPhotoEtiquetteSomafi(),
            'generale' => $formData->getPhoto2()
        ];

        foreach ($photoMappings as $type => $photoName) {
            if (!empty($photoName)) {
                $photos[$type] = $photoName;
            }
        }

        return $photos;
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
 * php bin/console app:debug-migration S140 RAP01
 * 
 * Cette commande va tester étape par étape le processus de migration
 * pour identifier exactement où ça échoue.
 */