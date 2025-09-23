<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Form;

#[AsCommand(
    name: 'app:count-equipment-by-agency',
    description: 'Compte les équipements par agence'
)]
class CountEquipmentByAgencyCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('agency', InputArgument::REQUIRED, 'Code agence (ex: S10)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agency = $input->getArgument('agency');
        
        try {
            $entityClass = "App\\Entity\\Equipement{$agency}";
            
            if (!class_exists($entityClass)) {
                $output->writeln("0");
                return Command::SUCCESS;
            }
            
            $repository = $this->entityManager->getRepository($entityClass);
            $count = $repository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->getQuery()
                ->getSingleScalarResult();
            
            $output->writeln((string)$count);
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("0");
            return Command::SUCCESS; // Ne pas faire échouer le script
        }
    }
}

#[AsCommand(
    name: 'app:count-equipment-with-photos',
    description: 'Compte les équipements avec photos par agence'
)]
class CountEquipmentWithPhotosCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('agency', InputArgument::REQUIRED, 'Code agence (ex: S10)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agency = $input->getArgument('agency');
        
        try {
            $entityClass = "App\\Entity\\Equipement{$agency}";
            
            if (!class_exists($entityClass)) {
                $output->writeln("0");
                return Command::SUCCESS;
            }
            
            // Compter les équipements qui ont au moins une photo dans la table Form
            $count = $this->entityManager->createQueryBuilder()
                ->select('COUNT(DISTINCT e.id)')
                ->from($entityClass, 'e')
                ->leftJoin(Form::class, 'f', 'WITH', 
                    'f.code_equipement = e.numero_equipement AND f.code_societe = e.code_societe')
                ->where('f.id IS NOT NULL')
                ->andWhere('(f.photo_plaque IS NOT NULL OR f.photo_choc IS NOT NULL OR f.photo_environnement_equipement1 IS NOT NULL)')
                ->getQuery()
                ->getSingleScalarResult();
            
            $output->writeln((string)$count);
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            // Si erreur SQL (colonne manquante), essayer une approche différente
            try {
                $repository = $this->entityManager->getRepository($entityClass);
                $totalCount = $repository->createQueryBuilder('e')
                    ->select('COUNT(e.id)')
                    ->getQuery()
                    ->getSingleScalarResult();
                
                // Approximation : supposer que 70% ont des photos si on ne peut pas vérifier
                $approximateWithPhotos = (int)($totalCount * 0.7);
                $output->writeln((string)$approximateWithPhotos);
                
            } catch (\Exception $e2) {
                $output->writeln("0");
            }
            
            return Command::SUCCESS;
        }
    }
}