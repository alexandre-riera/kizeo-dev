<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\HttpFoundation\Request;
use App\Controller\SimplifiedMaintenanceController;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\MaintenanceCacheService;

#[AsCommand(
    name: 'app:process-kizeo-forms',
    description: 'Enregistrer les équipements de toutes les agences en base de données'
)]
class ProcessKizeoFormsCommand extends Command
{
    protected static $defaultName = 'app:process-kizeo-forms';

    public function __construct(
        private SimplifiedMaintenanceController $maintenanceController,
        private EntityManagerInterface $entityManager,
        private MaintenanceCacheService $cacheService
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Traite les formulaires Kizeo Forms pour toutes les agences')
            ->addArgument('agency', InputArgument::OPTIONAL, 'Code agence spécifique (S10, S40, etc.)')
            ->addOption('chunk-size', 'c', InputOption::VALUE_OPTIONAL, 'Taille des chunks', 5)
            ->addOption('max-submissions', 'm', InputOption::VALUE_OPTIONAL, 'Nombre max de soumissions', 300)
            ->addOption('use-cache', null, InputOption::VALUE_OPTIONAL, 'Utiliser le cache', 'true')
            ->addOption('refresh-cache', null, InputOption::VALUE_OPTIONAL, 'Rafraîchir le cache', 'false')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agency = $input->getArgument('agency');
        $chunkSize = $input->getOption('chunk-size');
        $maxSubmissions = $input->getOption('max-submissions');
        $useCache = $input->getOption('use-cache');
        $refreshCache = $input->getOption('refresh-cache');
        
        $agencies = $agency ? [$agency] : [
            'S10', 'S40', 'S50', 'S60', 'S70', 'S80', 
            'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'
        ];

        $totalSuccess = 0;
        $totalErrors = 0;
        $startTime = time();

        $output->writeln('<info>🚀 Début du traitement des formulaires Kizeo Forms</info>');
        $output->writeln("📊 Agences à traiter: " . count($agencies));

        foreach ($agencies as $agencyCode) {
            $output->writeln("\n📋 Traitement de l'agence <comment>{$agencyCode}</comment>...");
            
            try {
                // Créer une Request simulée pour le contrôleur
                $request = new Request();
                $request->query->set('chunk_size', $chunkSize);
                $request->query->set('max_submissions', $maxSubmissions);
                $request->query->set('use_cache', $useCache);
                $request->query->set('refresh_cache', $refreshCache);

                // Appeler la méthode du contrôleur
                $response = $this->maintenanceController->processMaintenanceFixed(
                    $agencyCode,
                    $this->entityManager,
                    $request,
                    $this->cacheService
                );

                // Récupérer le contenu de la réponse JSON
                $responseData = json_decode($response->getContent(), true);

                if ($responseData['success'] ?? false) {
                    $equipments = $responseData['processing_summary']['total_equipments_processed'] ?? 0;
                    $submissions = $responseData['processing_summary']['processed_submissions'] ?? 0;
                    $time = $responseData['processing_summary']['processing_time'] ?? 'N/A';
                    
                    $output->writeln("   ✅ <info>Succès</info>: {$submissions} soumissions, {$equipments} équipements en {$time}");
                    
                    // Afficher les infos de cache si disponibles
                    if (isset($responseData['cache_info'])) {
                        $cacheHits = $responseData['cache_info']['individual_cache_hits'] ?? 0;
                        $fromCache = $responseData['cache_info']['submissions_from_cache'] ?? 0;
                        $output->writeln("   💾 Cache: {$cacheHits} hits individuels, {$fromCache} soumissions depuis cache");
                    }
                    
                    $totalSuccess++;
                } else {
                    $error = $responseData['error'] ?? 'Erreur inconnue';
                    $output->writeln("   ❌ <error>Erreur</error>: {$error}");
                    $totalErrors++;
                }

            } catch (\Exception $e) {
                $output->writeln("   ❌ <error>Exception</error>: " . $e->getMessage());
                $totalErrors++;
            }

            // Petite pause entre les agences pour éviter de surcharger l'API
            if (count($agencies) > 1) {
                sleep(2);
            }
        }

        $totalTime = time() - $startTime;
        $output->writeln("\n" . str_repeat('=', 50));
        $output->writeln("📈 <info>Résumé du traitement</info>");
        $output->writeln("   ✅ Agences traitées avec succès: <info>{$totalSuccess}</info>");
        $output->writeln("   ❌ Agences en erreur: <error>{$totalErrors}</error>");
        $output->writeln("   ⏱️  Temps total: <comment>{$totalTime}s</comment>");
        
        // Code de retour basé sur le succès
        return $totalErrors === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}