<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\FormRepository;

#[AsCommand(
    name: 'app:diagnose-migration',
    description: 'Diagnostique les problèmes de migration des photos'
)]
class DiagnoseMigrationCommand extends Command
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

        $io->title("🔍 Diagnostic de migration pour l'agence {$agency}");

        // Test 1: Vérifier l'entité Equipment
        $io->section('1️⃣ Vérification de l\'entité Equipment');
        $equipmentCheck = $this->checkEquipmentEntity($agency, $io);

        // Test 2: Vérifier les données Form
        $io->section('2️⃣ Vérification des données Form');
        $formCheck = $this->checkFormData($agency, $io);

        // Test 3: Vérifier la correspondance Equipment <-> Form
        $io->section('3️⃣ Vérification des correspondances');
        $matchCheck = $this->checkEquipmentFormMatches($agency, $io);

        // Test 4: Vérifier les photos dans Form
        $io->section('4️⃣ Vérification des photos');
        $photoCheck = $this->checkPhotosInForm($agency, $io);

        // Test 5: Exemple concret
        $io->section('5️⃣ Exemple d\'équipement');
        $this->showEquipmentExample($agency, $io);

        // Résumé
        $io->section('📊 Résumé du diagnostic');
        $allGood = $equipmentCheck && $formCheck && $matchCheck && $photoCheck;
        
        if ($allGood) {
            $io->success('✅ Diagnostic réussi - la migration devrait fonctionner');
        } else {
            $io->error('❌ Problèmes détectés - voir les détails ci-dessus');
        }

        return $allGood ? Command::SUCCESS : Command::FAILURE;
    }

    private function checkEquipmentEntity(string $agency, SymfonyStyle $io): bool
    {
        try {
            $entityClass = "App\\Entity\\Equipement{$agency}";
            
            if (!class_exists($entityClass)) {
                $io->error("❌ Classe {$entityClass} non trouvée");
                return false;
            }

            $repository = $this->entityManager->getRepository($entityClass);
            $totalCount = $repository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $io->success("✅ Entité trouvée avec {$totalCount} équipements");

            // Afficher quelques exemples
            $samples = $repository->createQueryBuilder('e')
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();

            $tableData = [];
            foreach ($samples as $equipment) {
                $tableData[] = [
                    $equipment->getNumeroEquipement(),
                    $equipment->getRaisonSociale(),
                    $equipment->getVisite(),
                    $equipment->getDateEnregistrement()
                ];
            }

            $io->table(['Numéro', 'Raison Sociale', 'Visite', 'Date'], $tableData);

            return true;

        } catch (\Exception $e) {
            $io->error("❌ Erreur accès entité: " . $e->getMessage());
            return false;
        }
    }

    private function checkFormData(string $agency, SymfonyStyle $io): bool
    {
        try {
            $totalForms = $this->entityManager->createQueryBuilder()
                ->select('COUNT(f.id)')
                ->from('App\Entity\Form', 'f')
                ->getQuery()
                ->getSingleScalarResult();

            $io->success("✅ Table Form accessible avec {$totalForms} entrées");

            // Chercher des entrées liées à cette agence
            $agencyForms = $this->entityManager->createQueryBuilder()
                ->select('COUNT(f.id)')
                ->from('App\Entity\Form', 'f')
                ->where('f.raison_sociale_visite LIKE :agency')
                ->setParameter('agency', '%\\' . 'CE%')
                ->getQuery()
                ->getSingleScalarResult();

            $io->writeln("ℹ️ Entrées Form avec pattern visite: {$agencyForms}");

            return true;

        } catch (\Exception $e) {
            $io->error("❌ Erreur accès table Form: " . $e->getMessage());
            return false;
        }
    }

    private function checkEquipmentFormMatches(string $agency, SymfonyStyle $io): bool
    {
        try {
            $entityClass = "App\\Entity\\Equipement{$agency}";
            $repository = $this->entityManager->getRepository($entityClass);
            
            // Prendre quelques équipements
            $equipments = $repository->createQueryBuilder('e')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            $matchCount = 0;
            $tableData = [];

            foreach ($equipments as $equipment) {
                $formData = $this->formRepository->findOneBy([
                    'equipment_id' => $equipment->getNumeroEquipement(),
                    'raison_sociale_visite' => $equipment->getRaisonSociale() . '\\' . $equipment->getVisite()
                ]);

                $hasMatch = $formData !== null;
                if ($hasMatch) $matchCount++;

                $tableData[] = [
                    $equipment->getNumeroEquipement(),
                    $equipment->getRaisonSociale() . '\\' . $equipment->getVisite(),
                    $hasMatch ? '✅' : '❌',
                    $hasMatch ? ($formData->getFormId() ?? 'N/A') : 'N/A'
                ];
            }

            $io->table(['Équipement', 'Raison Sociale\\Visite', 'Form trouvé', 'Form ID'], $tableData);

            if ($matchCount > 0) {
                $io->success("✅ {$matchCount}/5 équipements ont des données Form correspondantes");
                return true;
            } else {
                $io->error("❌ Aucun équipement n'a de données Form correspondantes");
                $io->text([
                    'Vérifiez:',
                    '- Le format de raison_sociale_visite dans la table form',
                    '- La correspondance des equipment_id',
                    '- Les données de test disponibles'
                ]);
                return false;
            }

        } catch (\Exception $e) {
            $io->error("❌ Erreur vérification correspondances: " . $e->getMessage());
            return false;
        }
    }

    private function checkPhotosInForm(string $agency, SymfonyStyle $io): bool
    {
        try {
            // Chercher des entrées Form avec des photos
            $formsWithPhotos = $this->entityManager->createQueryBuilder()
                ->select('f')
                ->from('App\Entity\Form', 'f')
                ->where('f.photo_compte_rendu IS NOT NULL')
                ->orWhere('f.photo_environnement_equipement1 IS NOT NULL')
                ->orWhere('f.photo_plaque IS NOT NULL')
                ->orWhere('f.photo_2 IS NOT NULL')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            if (empty($formsWithPhotos)) {
                $io->error("❌ Aucune entrée Form avec des photos trouvée");
                return false;
            }

            $io->success("✅ " . count($formsWithPhotos) . " entrées Form avec photos trouvées");

            $tableData = [];
            foreach ($formsWithPhotos as $form) {
                $photos = [];
                if ($form->getPhotoCompteRendu()) $photos[] = 'compte_rendu';
                if ($form->getPhotoEnvironnementEquipement1()) $photos[] = 'environnement';
                if ($form->getPhotoPlaque()) $photos[] = 'plaque';
                if ($form->getPhoto2()) $photos[] = 'generale';

                $tableData[] = [
                    $form->getEquipmentId() ?? 'N/A',
                    $form->getFormId() ?? 'N/A',
                    $form->getDataId() ?? 'N/A',
                    implode(', ', $photos)
                ];
            }

            $io->table(['Equipment ID', 'Form ID', 'Data ID', 'Photos'], $tableData);

            return true;

        } catch (\Exception $e) {
            $io->error("❌ Erreur vérification photos: " . $e->getMessage());
            return false;
        }
    }

    private function showEquipmentExample(string $agency, SymfonyStyle $io): void
    {
        try {
            $entityClass = "App\\Entity\\Equipement{$agency}";
            $repository = $this->entityManager->getRepository($entityClass);
            
            // Prendre le premier équipement
            $equipment = $repository->createQueryBuilder('e')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$equipment) {
                $io->warning("⚠️ Aucun équipement trouvé");
                return;
            }

            $io->writeln("📋 Exemple d'équipement:");
            $io->definitionList(
                ['Numéro' => $equipment->getNumeroEquipement()],
                ['Raison Sociale' => $equipment->getRaisonSociale()],
                ['Visite' => $equipment->getVisite()],
                ['Date' => $equipment->getDateEnregistrement()],
                ['Code Agence' => $equipment->getCodeAgence()]
            );

            // Chercher les données Form correspondantes
            $searchKey = $equipment->getRaisonSociale() . '\\' . $equipment->getVisite();
            $formData = $this->formRepository->findOneBy([
                'equipment_id' => $equipment->getNumeroEquipement(),
                'raison_sociale_visite' => $searchKey
            ]);

            if ($formData) {
                $io->writeln("✅ Données Form trouvées:");
                $io->definitionList(
                    ['Form ID' => $formData->getFormId() ?? 'N/A'],
                    ['Data ID' => $formData->getDataId() ?? 'N/A'],
                    ['Raison Sociale Visite' => $formData->getRaisonSocialeVisite() ?? 'N/A'],
                    ['Photo Compte Rendu' => $formData->getPhotoCompteRendu() ? '✅' : '❌'],
                    ['Photo Environnement' => $formData->getPhotoEnvironnementEquipement1() ? '✅' : '❌'],
                    ['Photo Plaque' => $formData->getPhotoPlaque() ? '✅' : '❌']
                );

                // Test de construction du chemin local
                $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
                $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
                $typeVisite = $equipment->getVisite();
                $codeEquipement = $equipment->getNumeroEquipement();

                $io->writeln("🗂️ Chemin local qui serait créé:");
                $basePath = $this->formRepository->getBaseImagePath();
                $localPath = "{$basePath}{$agency}/{$raisonSociale}/{$anneeVisite}/{$typeVisite}/{$codeEquipement}_compte_rendu.jpg";
                $io->writeln("   {$localPath}");

            } else {
                $io->error("❌ Aucune donnée Form trouvée");
                $io->writeln("🔍 Recherche effectuée avec:");
                $io->writeln("   - equipment_id: " . $equipment->getNumeroEquipement());
                $io->writeln("   - raison_sociale_visite: " . $searchKey);

                // Chercher des correspondances partielles
                $partialMatches = $this->entityManager->createQueryBuilder()
                    ->select('f.equipment_id, f.raison_sociale_visite')
                    ->from('App\Entity\Form', 'f')
                    ->where('f.equipment_id = :equipId')
                    ->setParameter('equipId', $equipment->getNumeroEquipement())
                    ->setMaxResults(3)
                    ->getQuery()
                    ->getResult();

                if (!empty($partialMatches)) {
                    $io->writeln("🔍 Correspondances partielles trouvées:");
                    foreach ($partialMatches as $match) {
                        $io->writeln("   - " . $match['equipment_id'] . " -> " . $match['raison_sociale_visite']);
                    }
                }
            }

        } catch (\Exception $e) {
            $io->error("❌ Erreur exemple équipement: " . $e->getMessage());
        }
    }
}

/**
 * COMMANDE DE TEST SIMPLE POUR VÉRIFIER UNE MIGRATION MANUELLE
 */

#[AsCommand(
    name: 'app:test-single-migration',
    description: 'Test de migration d\'un seul équipement'
)]
class TestSingleMigrationCommand extends Command
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
            ->addArgument('equipment_id', InputArgument::REQUIRED, 'ID de l\'équipement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agency = $input->getArgument('agency');
        $equipmentId = $input->getArgument('equipment_id');

        $io->title("🧪 Test de migration pour équipement {$equipmentId}");

        try {
            // 1. Trouver l'équipement
            $entityClass = "App\\Entity\\Equipement{$agency}";
            $repository = $this->entityManager->getRepository($entityClass);
            $equipment = $repository->findOneBy(['numero_equipement' => $equipmentId]);

            if (!$equipment) {
                $io->error("❌ Équipement {$equipmentId} non trouvé");
                return Command::FAILURE;
            }

            $io->success("✅ Équipement trouvé: " . $equipment->getRaisonSociale());

            // 2. Trouver les données Form
            $searchKey = $equipment->getRaisonSociale() . '\\' . $equipment->getVisite();
            $formData = $this->formRepository->findOneBy([
                'equipment_id' => $equipmentId,
                'raison_sociale_visite' => $searchKey
            ]);

            if (!$formData) {
                $io->error("❌ Données Form non trouvées pour la clé: {$searchKey}");
                
                // Chercher toutes les entrées pour cet équipement
                $allForms = $this->formRepository->findBy(['equipment_id' => $equipmentId]);
                if (!empty($allForms)) {
                    $io->writeln("🔍 Autres entrées Form trouvées:");
                    foreach ($allForms as $form) {
                        $io->writeln("   - " . $form->getRaisonSocialeVisite());
                    }
                }
                
                return Command::FAILURE;
            }

            $io->success("✅ Données Form trouvées");

            // 3. Vérifier les photos disponibles
            $photos = [
                'Compte Rendu' => $formData->getPhotoCompteRendu(),
                'Environnement' => $formData->getPhotoEnvironnementEquipement1(),
                'Plaque' => $formData->getPhotoPlaque(),
                'Générale' => $formData->getPhoto2()
            ];

            $availablePhotos = [];
            foreach ($photos as $type => $photoName) {
                if (!empty($photoName)) {
                    $availablePhotos[] = $type . ': ' . $photoName;
                }
            }

            if (empty($availablePhotos)) {
                $io->error("❌ Aucune photo disponible dans les données Form");
                return Command::FAILURE;
            }

            $io->success("✅ Photos disponibles:");
            foreach ($availablePhotos as $photo) {
                $io->writeln("   - " . $photo);
            }

            // 4. Calculer le chemin local
            $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
            $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
            $typeVisite = $equipment->getVisite();

            $basePath = $this->formRepository->getBaseImagePath();
            $localDir = "{$basePath}{$agency}/{$raisonSociale}/{$anneeVisite}/{$typeVisite}";

            $io->writeln("📁 Répertoire local qui serait créé:");
            $io->writeln("   {$localDir}");

            // 5. Vérifier les informations API
            if ($formData->getFormId() && $formData->getDataId()) {
                $io->success("✅ Informations API disponibles:");
                $io->writeln("   - Form ID: " . $formData->getFormId());
                $io->writeln("   - Data ID: " . $formData->getDataId());
                
                $firstPhoto = reset($photos);
                if ($firstPhoto) {
                    $apiUrl = "https://forms.kizeo.com/rest/v3/forms/{$formData->getFormId()}/data/{$formData->getDataId()}/medias/{$firstPhoto}";
                    $io->writeln("   - URL exemple: " . $apiUrl);
                }
            } else {
                $io->error("❌ Informations API manquantes (Form ID ou Data ID)");
                return Command::FAILURE;
            }

            $io->success("🎉 Migration possible pour cet équipement!");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

/**
 * UTILISATION DES COMMANDES DE DIAGNOSTIC :
 * 
 * # Diagnostic complet
 * php bin/console app:diagnose-migration S140
 * 
 * # Test d'un équipement spécifique  
 * php bin/console app:test-single-migration S140 RAP01
 * 
 * # Migration avec debug amélioré
 * php bin/console app:migrate-photos S140 --dry-run --debug --batch-size=5
 * 
 * Ces commandes vous aideront à identifier exactement pourquoi la migration échoue.
 */