<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ImageStorageService;
use App\Repository\FormRepository;

#[AsCommand(
    name: 'app:validate-photo-system',
    description: 'Valide que le système de photos locales est correctement configuré'
)]
class ValidatePhotoSystemCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImageStorageService $imageStorageService,
        private FormRepository $formRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🔍 Validation du système de photos locales');

        $allTestsPassed = true;

        // Test 1: Vérification des services
        $io->section('1️⃣ Vérification des services');
        if ($this->testServices($io)) {
            $io->success('✅ Tous les services sont correctement injectés');
        } else {
            $io->error('❌ Problème avec l\'injection des services');
            $allTestsPassed = false;
        }

        // Test 2: Vérification de la structure de répertoires
        $io->section('2️⃣ Vérification de la structure de répertoires');
        if ($this->testDirectoryStructure($io)) {
            $io->success('✅ Structure de répertoires correcte');
        } else {
            $io->error('❌ Problème avec la structure de répertoires');
            $allTestsPassed = false;
        }

        // Test 3: Test des permissions
        $io->section('3️⃣ Vérification des permissions');
        if ($this->testPermissions($io)) {
            $io->success('✅ Permissions correctes');
        } else {
            $io->warning('⚠️ Problème de permissions (peut être normal en développement)');
        }

        // Test 4: Test de création d'image
        $io->section('4️⃣ Test de création d\'image');
        if ($this->testImageCreation($io)) {
            $io->success('✅ Création d\'image fonctionnelle');
        } else {
            $io->error('❌ Problème avec la création d\'images');
            $allTestsPassed = false;
        }

        // Test 5: Test de la base de données
        $io->section('5️⃣ Vérification de la base de données');
        if ($this->testDatabase($io)) {
            $io->success('✅ Base de données accessible');
        } else {
            $io->error('❌ Problème avec la base de données');
            $allTestsPassed = false;
        }

        // Test 6: Test des entités d'équipement
        $io->section('6️⃣ Vérification des entités d\'équipement');
        if ($this->testEquipmentEntities($io)) {
            $io->success('✅ Entités d\'équipement trouvées');
        } else {
            $io->warning('⚠️ Aucune entité d\'équipement trouvée');
        }

        // Test 7: Variables d'environnement
        $io->section('7️⃣ Vérification des variables d\'environnement');
        if ($this->testEnvironmentVariables($io)) {
            $io->success('✅ Variables d\'environnement configurées');
        } else {
            $io->error('❌ Variables d\'environnement manquantes');
            $allTestsPassed = false;
        }

        // Résumé final
        $io->section('📊 Résumé de la validation');
        
        if ($allTestsPassed) {
            $io->success('🎉 Tous les tests sont passés ! Le système est prêt à l\'utilisation.');
            $this->displayUsageInstructions($io);
            return Command::SUCCESS;
        } else {
            $io->error('❌ Certains tests ont échoué. Veuillez corriger les problèmes avant de continuer.');
            $this->displayTroubleshootingTips($io);
            return Command::FAILURE;
        }
    }

    private function testServices(SymfonyStyle $io): bool
    {
        $services = [
            'EntityManager' => $this->entityManager,
            'ImageStorageService' => $this->imageStorageService,
            'FormRepository' => $this->formRepository
        ];

        $allGood = true;
        
        foreach ($services as $name => $service) {
            if ($service === null) {
                $io->writeln("❌ Service {$name} non injecté");
                $allGood = false;
            } else {
                $io->writeln("✅ Service {$name} correctement injecté");
            }
        }

        // Test des méthodes spécifiques
        try {
            $baseImagePath = $this->imageStorageService->getBaseImagePath();
            $io->writeln("✅ Chemin de base des images: {$baseImagePath}");
        } catch (\Exception $e) {
            $io->writeln("❌ Erreur méthode getBaseImagePath: " . $e->getMessage());
            $allGood = false;
        }

        return $allGood;
    }

    private function testDirectoryStructure(SymfonyStyle $io): bool
    {
        $baseImagePath = $this->imageStorageService->getBaseImagePath();
        $agencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];

        // Vérifier le répertoire de base
        if (!is_dir($baseImagePath)) {
            $io->writeln("❌ Répertoire de base manquant: {$baseImagePath}");
            
            // Tentative de création
            if (mkdir($baseImagePath, 0755, true)) {
                $io->writeln("✅ Répertoire de base créé: {$baseImagePath}");
            } else {
                $io->writeln("❌ Impossible de créer le répertoire de base");
                return false;
            }
        } else {
            $io->writeln("✅ Répertoire de base existant: {$baseImagePath}");
        }

        // Vérifier/créer les répertoires d'agences
        $createdCount = 0;
        foreach ($agencies as $agency) {
            $agencyDir = $baseImagePath . '/' . $agency;
            if (!is_dir($agencyDir)) {
                if (mkdir($agencyDir, 0755, true)) {
                    $createdCount++;
                }
            }
        }

        if ($createdCount > 0) {
            $io->writeln("✅ {$createdCount} répertoires d'agences créés");
        }

        $io->writeln("✅ Structure de répertoires validée");
        return true;
    }

    private function testPermissions(SymfonyStyle $io): bool
    {
        $baseImagePath = $this->imageStorageService->getBaseImagePath();
        
        // Test de lecture
        if (!is_readable($baseImagePath)) {
            $io->writeln("❌ Répertoire non lisible: {$baseImagePath}");
            return false;
        }

        // Test d'écriture
        if (!is_writable($baseImagePath)) {
            $io->writeln("❌ Répertoire non accessible en écriture: {$baseImagePath}");
            return false;
        }

        $io->writeln("✅ Permissions de lecture/écriture OK");
        
        // Afficher les permissions actuelles
        $perms = fileperms($baseImagePath);
        $permString = substr(sprintf('%o', $perms), -4);
        $io->writeln("ℹ️ Permissions actuelles: {$permString}");

        return true;
    }

    private function testImageCreation(SymfonyStyle $io): bool
    {
        try {
            // Créer une image de test
            $testImageContent = $this->createTestImageContent();
            
            $testPath = $this->imageStorageService->storeImage(
                'S140',
                'TEST_CLIENT',
                '2025',
                'CE1',
                'TEST_EQUIPMENT_validation',
                $testImageContent
            );

            $io->writeln("✅ Image de test créée: {$testPath}");

            // Vérifier que l'image existe
            if (file_exists($testPath)) {
                $io->writeln("✅ Image de test vérifiée sur le disque");
                
                // Vérifier l'intégrité
                if ($this->imageStorageService->verifyImageIntegrity($testPath)) {
                    $io->writeln("✅ Intégrité de l'image de test validée");
                } else {
                    $io->writeln("⚠️ Problème d'intégrité de l'image de test");
                }

                // Nettoyer l'image de test
                unlink($testPath);
                $io->writeln("✅ Image de test supprimée");

                return true;
            } else {
                $io->writeln("❌ Image de test non trouvée sur le disque");
                return false;
            }

        } catch (\Exception $e) {
            $io->writeln("❌ Erreur création image de test: " . $e->getMessage());
            return false;
        }
    }

    private function testDatabase(SymfonyStyle $io): bool
    {
        try {
            // Test de connexion à la base de données
            $connection = $this->entityManager->getConnection();
            $connection->connect();
            
            $io->writeln("✅ Connexion à la base de données OK");

            // Vérifier l'existence de la table Form
            $schemaManager = $connection->createSchemaManager();
            if ($schemaManager->tablesExist(['form'])) {
                $io->writeln("✅ Table 'form' trouvée");
                
                // Compter les entrées
                $count = $connection->executeQuery('SELECT COUNT(*) FROM form')->fetchOne();
                $io->writeln("ℹ️ Nombre d'entrées dans la table form: {$count}");
                
                return true;
            } else {
                $io->writeln("❌ Table 'form' non trouvée");
                return false;
            }

        } catch (\Exception $e) {
            $io->writeln("❌ Erreur base de données: " . $e->getMessage());
            return false;
        }
    }

    private function testEquipmentEntities(SymfonyStyle $io): bool
    {
        $agencies = ['S140', 'S50', 'S100']; // Test sur quelques agences
        $foundEntities = [];

        foreach ($agencies as $agency) {
            $entityClass = "App\\Entity\\Equipement{$agency}";
            
            try {
                $repository = $this->entityManager->getRepository($entityClass);
                $count = $repository->createQueryBuilder('e')
                    ->select('COUNT(e.id)')
                    ->getQuery()
                    ->getSingleScalarResult();
                
                $foundEntities[$agency] = $count;
                $io->writeln("✅ Entité {$entityClass} trouvée ({$count} équipements)");
                
            } catch (\Exception $e) {
                $io->writeln("⚠️ Entité {$entityClass} non trouvée ou problème: " . $e->getMessage());
            }
        }

        if (!empty($foundEntities)) {
            $totalEquipments = array_sum($foundEntities);
            $io->writeln("ℹ️ Total équipements trouvés: {$totalEquipments}");
            return true;
        }

        return false;
    }

    private function testEnvironmentVariables(SymfonyStyle $io): bool
    {
        $requiredVars = [
            'KIZEO_API_TOKEN' => 'Token API Kizeo Forms'
        ];

        $allPresent = true;

        foreach ($requiredVars as $var => $description) {
            $value = $_ENV[$var] ?? null;
            
            if ($value) {
                $maskedValue = substr($value, 0, 8) . '...' . substr($value, -4);
                $io->writeln("✅ {$var}: {$maskedValue}");
            } else {
                $io->writeln("❌ {$var} manquant ({$description})");
                $allPresent = false;
            }
        }

        return $allPresent;
    }

    private function createTestImageContent(): string
    {
        // Créer une image JPEG minimale de test (1x1 pixel)
        $image = imagecreate(1, 1);
        $white = imagecolorallocate($image, 255, 255, 255);
        
        ob_start();
        imagejpeg($image, null, 90);
        $content = ob_get_contents();
        ob_end_clean();
        
        imagedestroy($image);
        
        return $content;
    }

    private function displayUsageInstructions(SymfonyStyle $io): void
    {
        $io->section('🚀 Instructions d\'utilisation');
        
        $io->text([
            'Le système est maintenant prêt ! Voici les étapes recommandées :',
            '',
            '1. Migrer les photos existantes :',
            '   <comment>php bin/console app:migrate-photos S140</comment>',
            '',
            '2. Vérifier la migration :',
            '   <comment>curl "http://localhost/api/maintenance/photo-migration-report/S140"</comment>',
            '',
            '3. Tester la génération PDF optimisée :',
            '   <comment>curl "http://localhost/api/maintenance/generate-pdf-optimized/S140/RAP01"</comment>',
            '',
            '4. Programmer les tâches de maintenance :',
            '   <comment># Migration quotidienne</comment>',
            '   <comment>0 2 * * * php bin/console app:migrate-photos S140</comment>',
            '   <comment># Nettoyage hebdomadaire</comment>',
            '   <comment>0 3 * * 0 php bin/console app:migrate-photos S140 --clean-orphans</comment>',
            '',
            '5. Modifier vos contrôleurs PDF pour utiliser les nouvelles méthodes optimisées'
        ]);
    }

    private function displayTroubleshootingTips(SymfonyStyle $io): void
    {
        $io->section('🔧 Conseils de dépannage');
        
        $io->text([
            'Problèmes courants et solutions :',
            '',
            '1. Erreur de permissions :',
            '   <comment>sudo chown -R www-data:www-data public/img/</comment>',
            '   <comment>chmod -R 755 public/img/</comment>',
            '',
            '2. Services non injectés :',
            '   <comment>php bin/console cache:clear</comment>',
            '   <comment>Vérifiez la configuration dans config/services.yaml</comment>',
            '',
            '3. Variables d\'environnement manquantes :',
            '   <comment>Ajoutez KIZEO_API_TOKEN dans .env.local</comment>',
            '',
            '4. Entités d\'équipement non trouvées :',
            '   <comment>php bin/console doctrine:schema:update --dump-sql</comment>',
            '   <comment>Vérifiez que les entités EquipementS140, etc. existent</comment>',
            '',
            '5. Erreur de base de données :',
            '   <comment>php bin/console doctrine:database:create</comment>',
            '   <comment>php bin/console doctrine:migrations:migrate</comment>'
        ]);
    }
}

/**
 * COMMANDE DE TEST RAPIDE SUPPLÉMENTAIRE
 */

#[AsCommand(
    name: 'app:test-photo-download',
    description: 'Test rapide de téléchargement d\'une photo depuis Kizeo'
)]
class TestPhotoDownloadCommand extends Command
{
    public function __construct(
        private ImageStorageService $imageStorageService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('form_id', InputArgument::REQUIRED, 'ID du formulaire Kizeo')
            ->addArgument('data_id', InputArgument::REQUIRED, 'ID des données Kizeo')
            ->addArgument('photo_name', InputArgument::REQUIRED, 'Nom de la photo à télécharger')
            ->addOption('save-locally', 's', InputOption::VALUE_NONE, 'Sauvegarder la photo localement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $formId = $input->getArgument('form_id');
        $dataId = $input->getArgument('data_id');
        $photoName = $input->getArgument('photo_name');
        $saveLocally = $input->getOption('save-locally');

        $io->title("Test de téléchargement de photo depuis Kizeo");
        
        try {
            // Créer un client HTTP
            $client = HttpClient::create(['timeout' => 30]);
            
            $response = $client->request(
                'GET',
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/{$dataId}/medias/{$photoName}",
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"] ?? 'TOKEN_MANQUANT',
                    ],
                ]
            );

            $imageContent = $response->getContent();
            $imageSize = strlen($imageContent);
            
            $io->success("Photo téléchargée avec succès !");
            $io->definitionList(
                ['Taille de l\'image' => number_format($imageSize) . ' octets'],
                ['Type de contenu' => $response->getHeaders()['content-type'][0] ?? 'Inconnu'],
                ['Code de statut' => $response->getStatusCode()]
            );

            if ($saveLocally) {
                $localPath = $this->imageStorageService->storeImage(
                    'TEST',
                    'TEST_CLIENT',
                    '2025',
                    'CE1',
                    'test_download_' . date('His'),
                    $imageContent
                );
                
                $io->success("Photo sauvegardée localement : {$localPath}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Erreur lors du téléchargement : " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

/**
 * QUICK TEST : Commande simple pour vérifier que tout fonctionne
 */

#[AsCommand(
    name: 'app:quick-test',
    description: 'Test rapide du système de photos'
)]
class QuickTestCommand extends Command
{
    public function __construct(
        private ImageStorageService $imageStorageService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🚀 Test rapide du système');

        try {
            // Test 1: Chemin de base
            $basePath = $this->imageStorageService->getBaseImagePath();
            $io->success("✅ Chemin de base: {$basePath}");

            // Test 2: Création répertoire de test
            if (!is_dir($basePath . '/TEST')) {
                mkdir($basePath . '/TEST', 0755, true);
            }
            $io->success("✅ Répertoire de test créé");

            // Test 3: Statistiques
            $stats = $this->imageStorageService->getStorageStats();
            $io->success("✅ Statistiques récupérées: {$stats['total_images']} images");

            $io->success("🎉 Tous les tests rapides passés !");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

/**
 * UTILISATION :
 * 
 * # Validation complète
 * php bin/console app:validate-photo-system
 * 
 * # Test rapide
 * php bin/console app:quick-test
 * 
 * # Test téléchargement
 * php bin/console app:test-photo-download 1071913 225797975 "photo_name.jpg" --save-locally
 */