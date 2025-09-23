<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:test-kizeo-api',
    description: 'Test simple de connectivité avec l\'API Kizeo'
)]
class TestKizeoApiCommand extends Command
{
    protected function configure()
    {
        $this->addArgument('form_id', InputArgument::OPTIONAL, 'Form ID à tester', '1055931');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formId = $input->getArgument('form_id');
        
        try {
            $client = HttpClient::create();
            
            $output->writeln("=== TEST API KIZEO ===");
            $output->writeln("Form ID: $formId (S40 - St Etienne)");
            $output->writeln("Token: " . (empty($_ENV["KIZEO_API_TOKEN"]) ? "❌ MANQUANT" : "✅ Présent"));
            
            // Test 1: Récupérer les informations du formulaire
            $output->writeln("");
            $output->writeln("📋 Test 1: Informations du formulaire...");
            
            $formResponse = $client->request('GET', 
                'https://forms.kizeo.com/rest/v3/forms/' . $formId, 
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ]
                ]
            );
            
            $formInfo = $formResponse->toArray();
            $output->writeln("✅ Formulaire trouvé: " . ($formInfo['name'] ?? 'Nom non disponible'));
            $output->writeln("   Classe: " . ($formInfo['class'] ?? 'N/A'));
            
            // Test 2: Essayer différentes façons de récupérer les données
            $output->writeln("");
            $output->writeln("📊 Test 2: Récupération des données (méthode basique)...");
            
            $dataResponse = $client->request('GET', 
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data', 
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'query' => [
                        'limit' => 5
                    ]
                ]
            );
            
            $dataBasic = $dataResponse->toArray();
            $output->writeln("✅ Données récupérées (basique): " . count($dataBasic['data'] ?? []));
            
            // Test 3: Méthode advanced
            $output->writeln("");
            $output->writeln("📊 Test 3: Récupération des données (méthode advanced)...");
            
            $advancedResponse = $client->request('GET', 
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced', 
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'query' => [
                        'limit' => 5,
                        'format' => 'json'
                    ]
                ]
            );
            
            $dataAdvanced = $advancedResponse->toArray();
            $output->writeln("✅ Données récupérées (advanced): " . count($dataAdvanced['data'] ?? []));
            
            // Test 4: Avec POST (comme dans ta commande originale)
            $output->writeln("");
            $output->writeln("📊 Test 4: Récupération des données (POST advanced)...");
            
            $postResponse = $client->request('POST', 
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced', 
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'json' => [
                        'limit' => 5,
                        'format' => 'json'
                    ]
                ]
            );
            
            $dataPost = $postResponse->toArray();
            $output->writeln("✅ Données récupérées (POST): " . count($dataPost['data'] ?? []));
            
            // Afficher un exemple d'entrée si disponible
            $sampleData = null;
            if (!empty($dataAdvanced['data'])) {
                $sampleData = $dataAdvanced['data'];
            } elseif (!empty($dataBasic['data'])) {
                $sampleData = $dataBasic['data'];
            } elseif (!empty($dataPost['data'])) {
                $sampleData = $dataPost['data'];
            }
            
            if ($sampleData) {
                $output->writeln("");
                $output->writeln("📄 Exemple de données:");
                $firstEntry = $sampleData[0];
                $output->writeln("   Entry ID: " . ($firstEntry['entry_id'] ?? $firstEntry['_id'] ?? 'N/A'));
                $output->writeln("   Created: " . ($firstEntry['_created'] ?? 'N/A'));
                
                // Essayer de récupérer les détails de cette entrée
                if (isset($firstEntry['entry_id'])) {
                    $entryId = $firstEntry['entry_id'];
                } elseif (isset($firstEntry['_id'])) {
                    $entryId = $firstEntry['_id'];
                } else {
                    $entryId = null;
                }
                
                if ($entryId) {
                    $output->writeln("");
                    $output->writeln("🔍 Test 5: Récupération détails d'une entrée...");
                    
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
                    
                    if (isset($detailData['data']['fields'])) {
                        $fields = $detailData['data']['fields'];
                        $output->writeln("✅ Champs disponibles: " . implode(', ', array_keys($fields)));
                        
                        // Chercher les champs critiques
                        $criticalFields = ['id_client_', 'id_client', 'id_societe', 'code_agence'];
                        foreach ($criticalFields as $field) {
                            if (isset($fields[$field])) {
                                $value = $fields[$field]['value'] ?? 'vide';
                                $output->writeln("   ✅ $field: $value");
                            } else {
                                $output->writeln("   ❌ $field: non trouvé");
                            }
                        }
                    } else {
                        $output->writeln("❌ Pas de champs dans les détails");
                    }
                }
            } else {
                $output->writeln("❌ Aucune donnée trouvée dans le formulaire!");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}