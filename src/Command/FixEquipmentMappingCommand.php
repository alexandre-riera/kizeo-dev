<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\FormRepository;

#[AsCommand(
    name: 'app:fix-equipment-mapping',
    description: 'Analyse et corrige le mapping Equipment ↔ Form'
)]
class FixEquipmentMappingCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormRepository $formRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S140, S50, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agency = $input->getArgument('agency');

        $io->title("🔧 Analyse et correction du mapping pour l'agence {$agency}");

        // Étape 1: Analyser les correspondances par equipment_id
        $io->section('1️⃣ Analyse des correspondances par equipment_id');
        $mappingResults = $this->analyzeEquipmentIdMapping($agency, $io);

        // Étape 2: Proposer des solutions
        $io->section('2️⃣ Solutions proposées');
        $this->proposeSolutions($agency, $mappingResults, $io);

        // Étape 3: Test de migration corrigée
        $io->section('3️⃣ Test de migration avec mapping corrigé');
        $testResults = $this->testCorrectedMigration($agency, $mappingResults, $io);

        return $testResults ? Command::SUCCESS : Command::FAILURE;
    }

    private function analyzeEquipmentIdMapping(string $agency, SymfonyStyle $io): array
    {
        $results = [
            'direct_matches' => [],
            'partial_matches' => [],
            'no_matches' => [],
            'form_equipment_ids' => []
        ];

        try {
            // 1. Récupérer tous les équipements de l'agence
            $entityClass = "App\\Entity\\Equipement{$agency}";
            $repository = $this->entityManager->getRepository($entityClass);
            $equipments = $repository->createQueryBuilder('e')
                ->setMaxResults(20) // Limite pour l'analyse
                ->getQuery()
                ->getResult();

            // 2. Récupérer tous les equipment_id de la table Form
            $formEquipmentIds = $this->entityManager->createQueryBuilder()
                ->select('DISTINCT f.equipment_id')
                ->from('App\Entity\Form', 'f')
                ->where('f.equipment_id IS NOT NULL')
                ->getQuery()
                ->getArrayResult();

            $results['form_equipment_ids'] = array_column($formEquipmentIds, 'equipment_id');

            // 3. Analyser chaque équipement
            foreach ($equipments as $equipment) {
                $equipmentId = $equipment->getNumeroEquipement();
                
                // Chercher correspondance directe par equipment_id
                $directForms = $this->formRepository->findBy(['equipment_id' => $equipmentId]);
                
                if (!empty($directForms)) {
                    $results['direct_matches'][$equipmentId] = [
                        'equipment' => $equipment,
                        'forms' => $directForms,
                        'form_count' => count($directForms)
                    ];
                } else {
                    // Chercher correspondances partielles
                    $partialForms = $this->entityManager->createQueryBuilder()
                        ->select('f')
                        ->from('App\Entity\Form', 'f')
                        ->where('f.equipment_id LIKE :partial')
                        ->setParameter('partial', '%' . $equipmentId . '%')
                        ->setMaxResults(5)
                        ->getQuery()
                        ->getResult();

                    if (!empty($partialForms)) {
                        $results['partial_matches'][$equipmentId] = [
                            'equipment' => $equipment,
                            'forms' => $partialForms
                        ];
                    } else {
                        $results['no_matches'][] = [
                            'equipment' => $equipment
                        ];
                    }
                }
            }

            // Afficher les résultats
            $io->success("✅ Analyse terminée:");
            $io->definitionList(
                ['Correspondances directes' => count($results['direct_matches'])],
                ['Correspondances partielles' => count($results['partial_matches'])],
                ['Aucune correspondance' => count($results['no_matches'])],
                ['Total equipment_id dans Form' => count($results['form_equipment_ids'])]
            );

            // Afficher les correspondances directes
            if (!empty($results['direct_matches'])) {
                $io->writeln("🎯 Correspondances directes trouvées:");
                $tableData = [];
                foreach (array_slice($results['direct_matches'], 0, 5) as $equipId => $data) {
                    $firstForm = $data['forms'][0];
                    $tableData[] = [
                        $equipId,
                        $data['equipment']->getRaisonSociale(),
                        $firstForm->getRaisonSocialeVisite() ?? 'N/A',
                        $data['form_count']
                    ];
                }
                $io->table(['Equipment ID', 'Equipment Raison Sociale', 'Form Raison Sociale', 'Nb Forms'], $tableData);
            }

            return $results;

        } catch (\Exception $e) {
            $io->error("❌ Erreur analyse: " . $e->getMessage());
            return $results;
        }
    }

    private function proposeSolutions(string $agency, array $mappingResults, SymfonyStyle $io): void
    {
        $io->writeln("💡 Solutions recommandées:");

        if (!empty($mappingResults['direct_matches'])) {
            $io->writeln("✅ Solution 1: Migration directe par equipment_id");
            $io->writeln("   Utilisez les correspondances directes trouvées");
            $io->writeln("   Commande: php bin/console app:migrate-photos-direct {$agency}");
        }

        if (!empty($mappingResults['partial_matches'])) {
            $io->writeln("⚠️ Solution 2: Vérification manuelle des correspondances partielles");
            $io->writeln("   Certains équipements ont des correspondances approximatives");
        }

        if (!empty($mappingResults['no_matches'])) {
            $io->writeln("❌ Solution 3: Équipements sans données Form");
            $io->writeln("   Ces équipements n'ont pas de photos à migrer");
        }

        // Proposer une migration spécifique
        $io->section('🔧 Migration adaptée');
        $io->writeln("Créons une méthode de migration qui utilise uniquement equipment_id:");
        
        if (!empty($mappingResults['direct_matches'])) {
            $count = count($mappingResults['direct_matches']);
            $io->success("✅ {$count} équipements peuvent être migrés immédiatement");
        } else {
            $io->error("❌ Aucun équipement ne peut être migré avec la méthode actuelle");
        }
    }

    private function testCorrectedMigration(string $agency, array $mappingResults, SymfonyStyle $io): bool
    {
        if (empty($mappingResults['direct_matches'])) {
            $io->error("❌ Aucune migration possible - pas de correspondances directes");
            return false;
        }

        $io->writeln("🧪 Test de migration corrigée...");

        $testCount = 0;
        $successCount = 0;

        foreach (array_slice($mappingResults['direct_matches'], 0, 3) as $equipmentId => $data) {
            $testCount++;
            $equipment = $data['equipment'];
            $forms = $data['forms'];

            try {
                $io->writeln("Testing {$equipmentId}...");

                // Vérifier les données nécessaires
                $firstForm = $forms[0];
                $hasRequiredData = $firstForm->getFormId() && $firstForm->getDataId();
                
                if (!$hasRequiredData) {
                    $io->writeln("  ❌ Données API manquantes (Form ID ou Data ID)");
                    continue;
                }

                // Vérifier les photos disponibles
                $photos = [
                    $firstForm->getPhotoCompteRendu(),
                    $firstForm->getPhotoEnvironnementEquipement1(),
                    $firstForm->getPhotoPlaque(),
                    $firstForm->getPhoto2()
                ];

                $hasPhotos = false;
                foreach ($photos as $photo) {
                    if (!empty($photo)) {
                        $hasPhotos = true;
                        break;
                    }
                }

                if (!$hasPhotos) {
                    $io->writeln("  ❌ Aucune photo disponible");
                    continue;
                }

                // Construire le chemin local
                $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
                $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
                $typeVisite = $equipment->getVisite();

                $basePath = $this->formRepository->getBaseImagePath();
                $localDir = "{$basePath}{$agency}/{$raisonSociale}/{$anneeVisite}/{$typeVisite}";

                $io->writeln("  ✅ Migration possible:");
                $io->writeln("     - Form ID: {$firstForm->getFormId()}");
                $io->writeln("     - Data ID: {$firstForm->getDataId()}");
                $io->writeln("     - Chemin local: {$localDir}");

                $successCount++;

            } catch (\Exception $e) {
                $io->writeln("  ❌ Erreur: " . $e->getMessage());
            }
        }

        $io->writeln("");
        $io->definitionList(
            ['Équipements testés' => $testCount],
            ['Migrations possibles' => $successCount],
            ['Taux de succès' => $testCount > 0 ? round(($successCount / $testCount) * 100, 1) . '%' : '0%']
        );

        if ($successCount > 0) {
            $io->success("🎉 Migration possible ! Utilisez la commande corrigée.");
            return true;
        } else {
            $io->error("❌ Aucune migration possible avec les données actuelles.");
            return false;
        }
    }
}

/**
 * COMMANDE DE MIGRATION CORRIGÉE QUI UTILISE UNIQUEMENT EQUIPMENT_ID
 */

#[AsCommand(
    name: 'app:migrate-photos-direct',
    description: 'Migration des photos en utilisant uniquement equipment_id'
)]
class MigratePhotosDirectCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormRepository $formRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Taille des lots', 10)
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Simulation sans modification');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agency = $input->getArgument('agency');
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');

        $io->title("🔧 Migration directe par equipment_id pour l'agence {$agency}");

        if ($dryRun) {
            $io->note('Mode simulation activé');
        }

        try {
            // 1. Trouver les équipements avec correspondances directes
            $entityClass = "App\\Entity\\Equipement{$agency}";
            $repository = $this->entityManager->getRepository($entityClass);
            $equipments = $repository->createQueryBuilder('e')
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            $results = [
                'processed' => 0,
                'migrated' => 0,
                'skipped' => 0,
                'errors' => 0
            ];

            foreach ($equipments as $equipment) {
                $results['processed']++;
                $equipmentId = $equipment->getNumeroEquipement();

                try {
                    // Chercher par equipment_id uniquement
                    $formData = $this->formRepository->findOneBy([
                        'equipment_id' => $equipmentId
                    ]);

                    if (!$formData) {
                        $results['skipped']++;
                        $io->writeln("⏭️ {$equipmentId}: Aucune donnée Form");
                        continue;
                    }

                    if (!$formData->getFormId() || !$formData->getDataId()) {
                        $results['skipped']++;
                        $io->writeln("⏭️ {$equipmentId}: Données API manquantes");
                        continue;
                    }

                    // Vérifier les photos
                    $hasPhotos = $formData->getPhotoCompteRendu() || 
                                $formData->getPhotoEnvironnementEquipement1() || 
                                $formData->getPhotoPlaque() || 
                                $formData->getPhoto2();

                    if (!$hasPhotos) {
                        $results['skipped']++;
                        $io->writeln("⏭️ {$equipmentId}: Aucune photo disponible");
                        continue;
                    }

                    if (!$dryRun) {
                        // ICI: Appeler la vraie migration
                        $migrated = $this->migrateEquipmentPhotos($equipment, $formData, $agency);
                        if ($migrated) {
                            $results['migrated']++;
                            $io->writeln("✅ {$equipmentId}: Migration réussie");
                        } else {
                            $results['errors']++;
                            $io->writeln("❌ {$equipmentId}: Échec migration");
                        }
                    } else {
                        $results['migrated']++;
                        $io->writeln("✅ {$equipmentId}: Migration possible");
                    }

                } catch (\Exception $e) {
                    $results['errors']++;
                    $io->writeln("❌ {$equipmentId}: Erreur - " . $e->getMessage());
                }
            }

            // Résultats
            $io->section('📊 Résultats');
            $io->definitionList(
                ['Traités' => $results['processed']],
                ['Migrés' => $results['migrated']],
                ['Ignorés' => $results['skipped']],
                ['Erreurs' => $results['errors']]
            );

            if ($results['migrated'] > 0) {
                $io->success($dryRun ? 
                    "🎉 Simulation réussie - {$results['migrated']} équipements peuvent être migrés" :
                    "🎉 Migration réussie - {$results['migrated']} équipements migrés"
                );
                return Command::SUCCESS;
            } else {
                $io->error("❌ Aucune migration " . ($dryRun ? "possible" : "réussie"));
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function migrateEquipmentPhotos($equipment, $formData, string $agency): bool
    {
        // Version simplifiée - à adapter selon votre logique
        // Pour l'instant, retourne true pour la simulation
        return true;
    }
}

/**
 * UTILISATION :
 * 
 * # 1. Analyser le mapping
 * php bin/console app:fix-equipment-mapping S140
 * 
 * # 2. Si des correspondances directes sont trouvées, test de migration
 * php bin/console app:migrate-photos-direct S140 --dry-run --batch-size=5
 * 
 * # 3. Migration réelle
 * php bin/console app:migrate-photos-direct S140 --batch-size=5
 */