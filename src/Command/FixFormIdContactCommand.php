<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\FormRepository;

#[AsCommand(
    name: 'app:fix-form-id-contact',
    description: 'Corrige les enregistrements Form sans id_contact en base de données'
)]
class FixFormIdContactCommand extends Command
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
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Simulation sans modification')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Taille des lots', 100)
            ->setHelp('
Cette commande corrige tous les enregistrements Form qui ont un id_contact NULL ou vide
en récupérant l\'id_contact depuis l\'équipement correspondant.

Exemples:
  php bin/console app:fix-form-id-contact --dry-run
  php bin/console app:fix-form-id-contact --batch-size=50
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        $io->title('🔧 Correction des enregistrements Form sans id_contact');

        if ($dryRun) {
            $io->note('Mode simulation activé - aucune modification ne sera effectuée');
        }

        try {
            // 1. Analyser les données
            $analysis = $this->analyzeFormData();
            
            $io->section('📊 Analyse des données');
            $io->table(
                ['Statut', 'Nombre'],
                [
                    ['Forms total', $analysis['total']],
                    ['Avec id_contact', $analysis['with_id_contact']],
                    ['Sans id_contact', $analysis['without_id_contact']],
                    ['À corriger', $analysis['to_fix']]
                ]
            );

            if ($analysis['to_fix'] === 0) {
                $io->success('✅ Tous les enregistrements Form ont déjà un id_contact !');
                return Command::SUCCESS;
            }

            // 2. Confirmer l'opération
            if (!$dryRun) {
                if (!$io->confirm("Procéder à la correction de {$analysis['to_fix']} enregistrements ?", false)) {
                    $io->info('Opération annulée');
                    return Command::SUCCESS;
                }
            }

            // 3. Corriger les données
            $results = $this->fixFormsInBatches($analysis['to_fix'], $batchSize, $dryRun, $io);

            // 4. Afficher les résultats
            $io->section('📈 Résultats');
            $io->table(
                ['Opération', 'Nombre'],
                [
                    ['✅ Corrigés', $results['fixed']],
                    ['⏭️ Ignorés', $results['skipped']],
                    ['❌ Erreurs', $results['errors']],
                    ['📊 Total traités', $results['processed']]
                ]
            );

            if ($results['errors'] > 0) {
                $io->warning("⚠️ {$results['errors']} erreurs détectées. Consultez les logs pour plus de détails.");
            }

            if ($results['fixed'] > 0) {
                $io->success("✅ {$results['fixed']} enregistrements Form corrigés avec succès !");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("❌ Erreur : " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function analyzeFormData(): array
    {
        // Analyser les données Form existantes
        $qb = $this->entityManager->createQueryBuilder();
        
        // Total des Forms
        $total = $qb->select('COUNT(f.id)')
            ->from('App\Entity\Form', 'f')
            ->getQuery()
            ->getSingleScalarResult();

        // Forms avec id_contact
        $qb = $this->entityManager->createQueryBuilder();
        $withIdContact = $qb->select('COUNT(f.id)')
            ->from('App\Entity\Form', 'f')
            ->where('f.id_contact IS NOT NULL')
            ->andWhere('f.id_contact != :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

        // Forms sans id_contact
        $withoutIdContact = $total - $withIdContact;

        // Forms à corriger (ont code_equipement et raison_sociale_visite)
        $qb = $this->entityManager->createQueryBuilder();
        $toFix = $qb->select('COUNT(f.id)')
            ->from('App\Entity\Form', 'f')
            ->where('(f.id_contact IS NULL OR f.id_contact = :empty)')
            ->andWhere('f.code_equipement IS NOT NULL')
            ->andWhere('f.raison_sociale_visite IS NOT NULL')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'with_id_contact' => $withIdContact,
            'without_id_contact' => $withoutIdContact,
            'to_fix' => $toFix
        ];
    }

    private function fixFormsInBatches(int $totalToFix, int $batchSize, bool $dryRun, SymfonyStyle $io): array
    {
        $results = ['fixed' => 0, 'skipped' => 0, 'errors' => 0, 'processed' => 0];
        
        $progressBar = $io->createProgressBar($totalToFix);
        $progressBar->start();

        $offset = 0;
        
        while ($offset < $totalToFix) {
            // Récupérer un lot d'enregistrements à corriger
            $qb = $this->entityManager->createQueryBuilder();
            $forms = $qb->select('f')
                ->from('App\Entity\Form', 'f')
                ->where('(f.id_contact IS NULL OR f.id_contact = :empty)')
                ->andWhere('f.code_equipement IS NOT NULL')
                ->andWhere('f.raison_sociale_visite IS NOT NULL')
                ->setParameter('empty', '')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            if (empty($forms)) {
                break;
            }

            // Traiter chaque Form du lot
            foreach ($forms as $form) {
                try {
                    $fixed = $this->fixSingleForm($form, $dryRun);
                    if ($fixed) {
                        $results['fixed']++;
                    } else {
                        $results['skipped']++;
                    }
                    $results['processed']++;
                    $progressBar->advance();
                    
                } catch (\Exception $e) {
                    $results['errors']++;
                    $results['processed']++;
                    $progressBar->advance();
                }
            }

            // Flush des modifications pour ce lot
            if (!$dryRun) {
                $this->entityManager->flush();
            }

            $offset += $batchSize;
        }

        $progressBar->finish();
        $io->newLine(2);

        return $results;
    }

    private function fixSingleForm($form, bool $dryRun): bool
    {
        $codeEquipement = $form->getCodeEquipement();
        $raisonSocialeVisite = $form->getRaisonSocialeVisite();
        
        // Extraire le nom de la société et la visite
        $parts = explode('\\', $raisonSocialeVisite);
        $raisonSociale = $parts[0] ?? '';
        $visite = $parts[1] ?? '';
        
        if (empty($raisonSociale) || empty($visite)) {
            return false;
        }
        
        // Chercher l'équipement correspondant dans toutes les agences
        $equipment = $this->findEquipmentByCodeAndRaisonSociale($codeEquipement, $raisonSociale, $visite);
        
        if (!$equipment) {
            return false;
        }
        
        // Vérifier que l'équipement a bien un id_contact
        $idContact = $equipment->getIdContact();
        $idSociete = $equipment->getCodeSociete();
        
        if (empty($idContact)) {
            return false;
        }
        
        // Appliquer les corrections
        if (!$dryRun) {
            $form->setIdContact($idContact);
            if ($idSociete) {
                $form->setIdSociete($idSociete);
            }
            $this->entityManager->persist($form);
        }
        
        return true;
    }

    private function findEquipmentByCodeAndRaisonSociale(string $codeEquipement, string $raisonSociale, string $visite)
    {
        // Lister toutes les agences possibles
        $agences = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        foreach ($agences as $agence) {
            $entityClass = 'App\\Entity\\Equipement' . $agence;
            
            if (!class_exists($entityClass)) {
                continue;
            }
            
            try {
                $repository = $this->entityManager->getRepository($entityClass);
                
                // Chercher par numero_equipement ET raison_sociale avec visite
                $equipment = $repository->findOneBy([
                    'numero_equipement' => $codeEquipement
                ]);
                
                if ($equipment) {
                    // Vérifier que la raison sociale correspond
                    $equipmentRaisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? '';
                    if ($equipmentRaisonSociale === $raisonSociale && $equipment->getVisite() === $visite) {
                        return $equipment;
                    }
                }
                
            } catch (\Exception $e) {
                // Ignorer les erreurs pour cette agence et continuer
                continue;
            }
        }
        
        return null;
    }
}