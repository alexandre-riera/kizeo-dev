<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:analyze-missing-equipments',
    description: 'Analyser les équipements manquants entre Kizeo et BDD'
)]
class AnalyzeMissingEquipmentsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S40, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agency = $input->getArgument('agency');
        
        // Mapping des form_id et tables par agence
        $formMapping = ['S40' => '1055931'];
        $tableMapping = ['S40' => 'equipement_s40'];
        
        if (!isset($formMapping[$agency])) {
            $output->writeln("Agence non reconnue: $agency");
            return Command::FAILURE;
        }

        $formId = $formMapping[$agency];
        $tableName = $tableMapping[$agency];
        
        try {
            $client = HttpClient::create();
            
            // 1. Récupérer tous les formulaires Kizeo
            $response = $client->request('GET', 
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data', 
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'query' => ['limit' => 1000] // Récupérer plus de formulaires
                ]
            );
            
            $kizeoData = $response->toArray();
            $submissions = $kizeoData['data'] ?? [];
            
            $output->writeln("=== ANALYSE ÉQUIPEMENTS MANQUANTS $agency ===");
            $output->writeln("📋 Formulaires Kizeo trouvés: " . count($submissions));
            
            // 2. Analyser chaque submission et construire des statistiques
            $missingContacts = [];
            $existingContacts = [];
            $totalKizeoEquipments = 0;
            $potentialMatches = [];
            
            foreach ($submissions as $submission) {
                $entryId = $submission['id'];
                
                // Récupérer les détails
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
                
                $idContact = $fields['id_client_']['value'] ?? '';
                $clientName = $fields['nom_client']['value'] ?? '';
                
                // Compter les équipements dans ce formulaire
                $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
                $offContractEquipments = $fields['tableau2']['value'] ?? [];
                $equipmentCount = count($contractEquipments) + count($offContractEquipments);
                
                $totalKizeoEquipments += $equipmentCount;
                
                if (!empty($idContact)) {
                    // Vérifier si ce contact existe en BDD
                    $connection = $this->entityManager->getConnection();
                    $bddCount = $connection->executeQuery(
                        "SELECT COUNT(*) FROM $tableName WHERE id_contact = ?", 
                        [$idContact]
                    )->fetchOne();
                    
                    if ($bddCount > 0) {
                        $existingContacts[$idContact] = [
                            'name' => $clientName,
                            'bdd_equipments' => $bddCount,
                            'kizeo_equipments' => $equipmentCount
                        ];
                    } else {
                        // Chercher des correspondances potentielles par nom
                        $possibleMatches = $connection->executeQuery(
                            "SELECT id_contact, raison_sociale, COUNT(*) as count FROM $tableName 
                             WHERE UPPER(raison_sociale) LIKE UPPER(?) 
                             GROUP BY id_contact, raison_sociale LIMIT 3", 
                            ['%' . $clientName . '%']
                        )->fetchAllAssociative();
                        
                        $missingContacts[$idContact] = [
                            'name' => $clientName,
                            'kizeo_equipments' => $equipmentCount,
                            'possible_matches' => $possibleMatches
                        ];
                    }
                }
            }
            
            // 3. Afficher les résultats
            $output->writeln("");
            $output->writeln("📊 RÉSUMÉ:");
            $output->writeln("   🔧 Total équipements Kizeo: $totalKizeoEquipments");
            $output->writeln("   ✅ Contacts existants: " . count($existingContacts));
            $output->writeln("   ❌ Contacts manquants: " . count($missingContacts));
            
            $output->writeln("");
            $output->writeln("=== CONTACTS EXISTANTS (avec différences) ===");
            foreach ($existingContacts as $idContact => $data) {
                if ($data['kizeo_equipments'] != $data['bdd_equipments']) {
                    $output->writeln("ID: $idContact | {$data['name']}");
                    $output->writeln("   📋 Kizeo: {$data['kizeo_equipments']} équipements");
                    $output->writeln("   💾 BDD: {$data['bdd_equipments']} équipements");
                    $output->writeln("   ⚠️ Différence: " . ($data['kizeo_equipments'] - $data['bdd_equipments']));
                }
            }
            
            $output->writeln("");
            $output->writeln("=== TOP 10 CONTACTS MANQUANTS (plus d'équipements) ===");
            
            // Trier par nombre d'équipements décroissant
            uasort($missingContacts, function($a, $b) {
                return $b['kizeo_equipments'] <=> $a['kizeo_equipments'];
            });
            
            $count = 0;
            foreach ($missingContacts as $idContact => $data) {
                if ($count >= 10) break;
                
                $output->writeln("ID: $idContact | {$data['name']} | {$data['kizeo_equipments']} équipements");
                
                if (!empty($data['possible_matches'])) {
                    $output->writeln("   🔍 Correspondances possibles:");
                    foreach ($data['possible_matches'] as $match) {
                        $output->writeln("      - ID: {$match['id_contact']} | {$match['raison_sociale']} | {$match['count']} équipements");
                    }
                }
                $output->writeln("");
                $count++;
            }
            
            // 4. Calculer le potentiel de récupération
            $totalMissingEquipments = array_sum(array_column($missingContacts, 'kizeo_equipments'));
            $currentBddEquipments = $this->entityManager->getConnection()
                ->executeQuery("SELECT COUNT(*) FROM $tableName")->fetchOne();
            
            $output->writeln("=== POTENTIEL DE RÉCUPÉRATION ===");
            $output->writeln("   📊 Équipements actuels en BDD: $currentBddEquipments");
            $output->writeln("   🚀 Équipements manquants Kizeo: $totalMissingEquipments");
            $output->writeln("   💡 Potentiel total: " . ($currentBddEquipments + $totalMissingEquipments));
            $output->writeln("   📈 Augmentation possible: " . 
                round(($totalMissingEquipments / $currentBddEquipments) * 100, 1) . "%");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}