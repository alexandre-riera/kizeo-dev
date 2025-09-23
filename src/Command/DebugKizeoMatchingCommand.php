<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use App\Controller\SimplifiedMaintenanceController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:debug-kizeo-matching',
    description: 'Debug la correspondance entre Kizeo et les équipements BDD'
)]
class DebugKizeoMatchingCommand extends Command
{
    public function __construct(
        private SimplifiedMaintenanceController $maintenanceController,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S40, S50, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agency = $input->getArgument('agency');
        
        // Mapping des form_id par agence
        $formMapping = [
            'S10' => '1090092',
            'S40' => '1055931',  
            'S50' => '1065302',
            'S60' => '1055932',
            'S70' => '1057365',
            'S80' => '1053175',
            'S100' => '1071913',
            'S120' => '1062555',
            'S130' => '1057880',
            'S140' => '1088761',
            'S150' => '1057408',
            'S160' => '1060720',
            'S170' => '1094209',
        ];

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

        if (!isset($formMapping[$agency])) {
            $output->writeln("Agence non reconnue: $agency");
            return Command::FAILURE;
        }

        $formId = $formMapping[$agency];
        $tableName = $agencyTableMapping[$agency];
        
        try {
            $output->writeln("=== DEBUG CORRESPONDANCE KIZEO - AGENCE $agency ===");
            
            // 1. Récupérer quelques submissions Kizeo (méthode basique qui fonctionne)
            $client = HttpClient::create();
            $response = $client->request('GET', 
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data', 
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'query' => [
                        'limit' => 5 // Seulement 5 pour debug
                    ]
                ]
            );
            
            $kizeoData = $response->toArray();
            $submissions = $kizeoData['data'] ?? [];
            
            $output->writeln("📋 Trouvé " . count($submissions) . " submissions Kizeo pour debug");
            
            // 2. Analyser chaque submission
            foreach ($submissions as $index => $submission) {
                $output->writeln("");
                $output->writeln("--- SUBMISSION " . ($index + 1) . " ---");
                $output->writeln("Entry ID: " . ($submission['_id'] ?? 'N/A'));
                
                // Récupérer les détails de cette entrée
                $entryId = $submission['id'];
                $detailResponse = $client->request('GET', 
                    'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId, 
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ]
                    ]
                );
                
                $detailData = $detailResponse->toArray();
                $fields = $detailData['data']['fields'] ?? [];
                
                // Extraire les données cruciales
                $idContact = $fields['id_client_']['value'] ?? '';
                $idSociete = $fields['id_societe']['value'] ?? '';
                
                $output->writeln("ID Contact Kizeo: " . ($idContact ?: 'MANQUANT'));
                $output->writeln("ID Société Kizeo: " . ($idSociete ?: 'MANQUANT'));
                
                // Vérifier si cet id_contact existe dans la BDD
                if (!empty($idContact)) {
                    $connection = $this->entityManager->getConnection();
                    $equipmentCount = $connection->executeQuery(
                        "SELECT COUNT(*) FROM $tableName WHERE id_contact = ?", 
                        [$idContact]
                    )->fetchOne();
                    
                    $output->writeln("✅ Équipements BDD avec cet id_contact: $equipmentCount");
                    
                    if ($equipmentCount > 0) {
                        // Lister quelques équipements
                        $equipments = $connection->executeQuery(
                            "SELECT numero_equipement, raison_sociale FROM $tableName WHERE id_contact = ? LIMIT 3", 
                            [$idContact]
                        )->fetchAllAssociative();
                        
                        $output->writeln("   Exemples d'équipements:");
                        foreach ($equipments as $eq) {
                            $output->writeln("   - " . $eq['numero_equipement'] . " (" . $eq['raison_sociale'] . ")");
                        }
                    }
                } else {
                    $output->writeln("❌ ID Contact manquant dans Kizeo!");
                }
                
                // Analyser les équipements dans ce formulaire
                $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
                $offContractEquipments = $fields['tableau2']['value'] ?? [];
                
                $totalFormEquipments = count($contractEquipments) + count($offContractEquipments);
                $output->writeln("🔧 Équipements dans ce formulaire: $totalFormEquipments");
                $output->writeln("   - Sous contrat: " . count($contractEquipments));
                $output->writeln("   - Hors contrat: " . count($offContractEquipments));
                
                // Exemple d'équipement sous contrat
                if (!empty($contractEquipments)) {
                    $firstEquipment = $contractEquipments[0];
                    $equipmentPath = $firstEquipment['equipement']['path'] ?? '';
                    $output->writeln("   Exemple équipement: " . $equipmentPath);
                }
            }
            
            // 3. Statistiques globales BDD
            $output->writeln("");
            $output->writeln("=== STATISTIQUES BDD $agency ===");
            
            $connection = $this->entityManager->getConnection();
            
            // Compter les id_contact uniques
            $uniqueContacts = $connection->executeQuery(
                "SELECT COUNT(DISTINCT id_contact) FROM $tableName"
            )->fetchOne();
            
            // Top 5 des id_contact avec le plus d'équipements
            $topContacts = $connection->executeQuery("
                SELECT id_contact, COUNT(*) as count, raison_sociale
                FROM $tableName 
                GROUP BY id_contact, raison_sociale
                ORDER BY count DESC 
                LIMIT 5
            ")->fetchAllAssociative();
            
            $output->writeln("👥 ID Contacts uniques dans BDD: $uniqueContacts");
            $output->writeln("🏆 Top 5 clients avec le plus d'équipements:");
            
            foreach ($topContacts as $contact) {
                $output->writeln("   - ID: " . $contact['id_contact'] . 
                               " | Équipements: " . $contact['count'] . 
                               " | Raison sociale: " . $contact['raison_sociale']);
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}