<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:debug-process',
    description: 'Debug le processus complet de traitement',
)]
class DebugProcessCommand extends Command
{
    protected static $defaultName = 'app:debug-process';

    private $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = HttpClient::create();
    }

    protected function configure()
    {
        $this
            ->setDescription('Debug le processus complet de traitement')
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S100)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agencyCode = $input->getArgument('agency');
        
        // Form ID pour S100
        $formId = '1071913';
        $maxSubmissions = 3; // Petit nombre pour debug

        $output->writeln("🔍 Debug du processus complet pour {$agencyCode}");

        try {
            // 1. Récupérer la liste des soumissions (comme dans getFormSubmissionsFixed)
            $output->writeln("📋 Étape 1: Récupération de la liste des soumissions...");
            
            $submissions = [];
            $offset = 0;
            $batchSize = 50;
            
            while (count($submissions) < $maxSubmissions) {
                $response = $this->client->request('GET', 
                    "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/all?limit={$batchSize}&offset={$offset}",
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 30
                    ]
                );

                $listData = $response->toArray();
                
                if (empty($listData['data'])) {
                    break;
                }

                foreach ($listData['data'] as $entry) {
                    if (count($submissions) >= $maxSubmissions) {
                        break;
                    }
                    
                    $submissions[] = [
                        'form_id' => $formId,
                        'entry_id' => $entry['_id'] ?? $entry['id'],
                        'client_name' => 'À déterminer',
                        'date' => $entry['answer_time'] ?? 'N/A',
                        'technician' => 'À déterminer'
                    ];
                }
                
                $offset += $batchSize;
                
                if (count($listData['data']) < $batchSize) {
                    break;
                }
            }

            $output->writeln("   ✅ " . count($submissions) . " soumissions récupérées");

            // 2. Traiter chaque soumission individuellement
            foreach ($submissions as $index => $submission) {
                $output->writeln("\n🔧 Traitement soumission " . ($index + 1) . ": " . $submission['entry_id']);
                
                // Récupérer les détails
                $detailResponse = $this->client->request('GET',
                    "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/{$submission['entry_id']}",
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 30
                    ]
                );

                $detailData = $detailResponse->toArray();
                $fields = $detailData['data']['fields'] ?? [];

                // Analyser les équipements
                $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
                $offContractEquipments = $fields['tableau2']['value'] ?? [];

                $output->writeln("   📊 Équipements trouvés:");
                $output->writeln("      • Sous contrat: " . count($contractEquipments));
                $output->writeln("      • Hors contrat: " . count($offContractEquipments));

                // Debug des données communes
                $commonData = [
                    'id_societe' => $fields['id_societe']['value'] ?? 'MANQUANT',
                    'nom_client' => $fields['nom_client']['value'] ?? 'MANQUANT',
                    'code_agence' => $fields['code_agence']['value'] ?? 'MANQUANT',
                    'id_client_' => $fields['id_client_']['value'] ?? 'MANQUANT',
                    'trigramme' => $fields['trigramme']['value'] ?? 'MANQUANT',
                    'date_et_heure1' => $fields['date_et_heure1']['value'] ?? 'MANQUANT'
                ];

                $output->writeln("   📋 Données communes:");
                foreach ($commonData as $key => $value) {
                    $status = $value === 'MANQUANT' ? '<e>❌</e>' : '<info>✅</info>';
                    $output->writeln("      {$status} {$key}: {$value}");
                }

                // Analyse détaillée du premier équipement sous contrat
                if (!empty($contractEquipments)) {
                    $output->writeln("   🔍 Analyse détaillée du premier équipement sous contrat:");
                    $firstEquipment = $contractEquipments[0];
                    
                    $equipmentFields = [
                        'equipement' => 'Numéro équipement',
                        'reference7' => 'Libellé',
                        'reference2' => 'Mise en service',
                        'reference6' => 'Numéro série',
                        'reference5' => 'Marque',
                        'reference1' => 'Hauteur',
                        'reference3' => 'Largeur',
                        'localisation_site_client' => 'Localisation',
                        'mode_fonctionnement_2' => 'Mode fonctionnement',
                        'etat' => 'État'
                    ];

                    $validFields = 0;
                    $totalFields = count($equipmentFields);

                    foreach ($equipmentFields as $field => $description) {
                        if (isset($firstEquipment[$field]['value']) && !empty($firstEquipment[$field]['value'])) {
                            $value = $firstEquipment[$field]['value'];
                            $output->writeln("      ✅ {$description}: {$value}");
                            $validFields++;
                        } else {
                            $output->writeln("      ❌ {$description}: MANQUANT");
                        }
                    }

                    $completeness = round(($validFields / $totalFields) * 100);
                    $output->writeln("   📈 Complétude des données: {$completeness}% ({$validFields}/{$totalFields})");

                    // Diagnostic de pourquoi l'équipement ne serait pas traité
                    $output->writeln("   🔍 Diagnostic des problèmes potentiels:");
                    
                    if (empty($commonData['id_client_']) || $commonData['id_client_'] === 'MANQUANT') {
                        $output->writeln("      ❌ CRITIQUE: id_client_ manquant - empêche l'enregistrement");
                    }
                    
                    if (empty($firstEquipment['equipement']['value'])) {
                        $output->writeln("      ❌ CRITIQUE: numéro d'équipement manquant");
                    }
                    
                    if ($completeness < 50) {
                        $output->writeln("      ⚠️  ATTENTION: Données incomplètes ({$completeness}%)");
                    }

                } else {
                    $output->writeln("   ❌ Aucun équipement sous contrat à analyser");
                }

                // Simulation de l'enregistrement
                $output->writeln("   🧪 Simulation d'enregistrement:");
                if ($this->simulateProcessing($contractEquipments, $offContractEquipments, $commonData)) {
                    $output->writeln("      ✅ Simulation: Équipement serait enregistré");
                } else {
                    $output->writeln("      ❌ Simulation: Équipement ne serait PAS enregistré");
                }
            }

            // 3. Recommandations
            $output->writeln("\n💡 Recommandations:");
            $output->writeln("   1. Vérifier que setRealContractData() utilise la nouvelle logique");
            $output->writeln("   2. Vérifier que setRealCommonData() utilise les bons noms de champs");
            $output->writeln("   3. Ajouter des logs dans processSingleSubmissionWithDeduplication()");
            $output->writeln("   4. Vérifier les conditions de validation des données");

        } catch (\Exception $e) {
            $output->writeln("<e>Erreur: " . $e->getMessage() . "</e>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function simulateProcessing(array $contractEquipments, array $offContractEquipments, array $commonData): bool
    {
        // Critères de base pour qu'un équipement soit traité
        if (empty($contractEquipments) && empty($offContractEquipments)) {
            return false;
        }

        // Vérifier les données critiques
        if (empty($commonData['id_client_']) || $commonData['id_client_'] === 'MANQUANT') {
            return false;
        }

        if (empty($commonData['code_agence']) || $commonData['code_agence'] === 'MANQUANT') {
            return false;
        }

        // Au moins un équipement avec données minimales
        foreach ($contractEquipments as $equipment) {
            if (!empty($equipment['equipement']['value'])) {
                return true;
            }
        }

        return false;
    }
}