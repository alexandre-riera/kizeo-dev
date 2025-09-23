<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:count-equipment-with-photos',
    description: 'Compte les équipements avec photos par agence'
)]
class CountEquipmentWithPhotosCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S10, S40, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agency = $input->getArgument('agency');
        
        // Mapping des tables d'équipements par agence
        $agencyTableMapping = [
            'S10' => 'equipement_s10',
            'S40' => 'equipement_s40', 
            'S50' => 'equipement_s50',
            'S60' => 'equipement_s60',
            'S70' => 'equipement_s70',
            'S80' => 'equipement_s80',
            'S100' => 'equipement_s100',
            'S120' => 'equipement_s120',
            'S130' => 'equipement_s130',
            'S140' => 'equipement_s140',
            'S150' => 'equipement_s150',
            'S160' => 'equipement_s160',
            'S170' => 'equipement_s170',
        ];

        if (!isset($agencyTableMapping[$agency])) {
            $output->writeln("Agence non reconnue: $agency");
            return Command::FAILURE;
        }

        $tableName = $agencyTableMapping[$agency];
        
        try {
            $connection = $this->entityManager->getConnection();
            
            // Compter les équipements qui ont au moins une photo associée dans la table form
            $sql = "
                SELECT COUNT(DISTINCT e.numero_equipement) as count 
                FROM $tableName e 
                INNER JOIN form f ON f.code_equipement = e.numero_equipement 
                WHERE f.id_contact = e.id_contact 
                AND (
                    f.photo_2 IS NOT NULL OR 
                    f.photo_etiquette_somafi IS NOT NULL OR 
                    f.photo_environnement_equipement1 IS NOT NULL OR
                    f.photo_plaque IS NOT NULL OR
                    f.photo_compte_rendu IS NOT NULL
                )
            ";
            
            $result = $connection->executeQuery($sql);
            $count = $result->fetchOne();
            
            $output->writeln((string)$count);
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}