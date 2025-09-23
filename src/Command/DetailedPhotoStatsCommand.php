<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:detailed-photo-stats',
    description: 'Statistiques détaillées des photos par agence'
)]
class DetailedPhotoStatsCommand extends Command
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
            
            // 1. Total équipements
            $totalEquipments = $connection->executeQuery("SELECT COUNT(*) FROM $tableName")->fetchOne();
            
            // 2. Équipements avec entrée dans form
            $equipmentsInForm = $connection->executeQuery("
                SELECT COUNT(DISTINCT e.numero_equipement) 
                FROM $tableName e 
                INNER JOIN form f ON f.code_equipement = e.numero_equipement 
                WHERE f.id_contact = e.id_contact
            ")->fetchOne();
            
            // 3. Équipements avec au moins une photo (liste principale)
            $equipmentsWithMainPhotos = $connection->executeQuery("
                SELECT COUNT(DISTINCT e.numero_equipement) 
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
            ")->fetchOne();
            
            // 4. Équipements avec toutes les photos possibles
            $equipmentsWithAnyPhotos = $connection->executeQuery("
                SELECT COUNT(DISTINCT e.numero_equipement) 
                FROM $tableName e 
                INNER JOIN form f ON f.code_equipement = e.numero_equipement 
                WHERE f.id_contact = e.id_contact 
                AND (
                    f.photo_2 IS NOT NULL OR f.photo_etiquette_somafi IS NOT NULL OR 
                    f.photo_environnement_equipement1 IS NOT NULL OR f.photo_plaque IS NOT NULL OR 
                    f.photo_compte_rendu IS NOT NULL OR f.photo_choc IS NOT NULL OR 
                    f.photo_choc_montant IS NOT NULL OR f.photo_moteur IS NOT NULL OR 
                    f.photo_carte IS NOT NULL OR f.photo_rail IS NOT NULL OR
                    f.photo_axe IS NOT NULL OR f.photo_serrure IS NOT NULL OR
                    f.photo_feux IS NOT NULL OR f.photo_bache IS NOT NULL OR
                    f.photo_marquage_au_sol IS NOT NULL OR f.photo_coffret_de_commande IS NOT NULL
                )
            ")->fetchOne();
            
            // 5. Détail par type de photo
            $photoStats = [];
            $photoColumns = [
                'photo_2' => 'Photo générale',
                'photo_etiquette_somafi' => 'Étiquette Somafi', 
                'photo_environnement_equipement1' => 'Environnement',
                'photo_plaque' => 'Plaque',
                'photo_compte_rendu' => 'Compte rendu',
                'photo_choc' => 'Choc',
                'photo_moteur' => 'Moteur',
                'photo_carte' => 'Carte'
            ];
            
            foreach ($photoColumns as $column => $label) {
                $count = $connection->executeQuery("
                    SELECT COUNT(DISTINCT e.numero_equipement) 
                    FROM $tableName e 
                    INNER JOIN form f ON f.code_equipement = e.numero_equipement 
                    WHERE f.id_contact = e.id_contact AND f.$column IS NOT NULL
                ")->fetchOne();
                $photoStats[$label] = $count;
            }
            
            // Affichage des résultats
            $output->writeln("=== STATISTIQUES PHOTOS AGENCE $agency ===");
            $output->writeln("📊 Total équipements: $totalEquipments");
            $output->writeln("📋 Équipements dans table form: $equipmentsInForm");
            $output->writeln("📸 Équipements avec photos principales: $equipmentsWithMainPhotos");
            $output->writeln("🖼️  Équipements avec n'importe quelle photo: $equipmentsWithAnyPhotos");
            $output->writeln("");
            $output->writeln("=== DÉTAIL PAR TYPE DE PHOTO ===");
            foreach ($photoStats as $label => $count) {
                $output->writeln("   $label: $count");
            }
            
            // Pour le script bash, on retourne juste le nombre avec photos principales
            $output->writeln("");
            $output->writeln($equipmentsWithMainPhotos);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}