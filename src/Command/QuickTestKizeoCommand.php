<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:quick-test-kizeo',
    description: 'Test rapide extraction équipements Kizeo',
)]
class QuickTestKizeoCommand extends Command
{
    protected static $defaultName = 'app:quick-test-kizeo';

    private $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = HttpClient::create();
    }

    protected function configure()
    {
        $this
            ->setDescription('Test rapide extraction équipements Kizeo')
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S100)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agencyCode = $input->getArgument('agency');
        
        // Form ID pour S100
        $formId = '1071913';
        $entryId = '234979977'; // Celui du debug

        $output->writeln("🔧 Test d'extraction des équipements pour {$agencyCode}");

        try {
            // Récupérer les détails de la soumission
            $detailResponse = $this->client->request('GET',
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/{$entryId}",
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            $fields = $detailData['data']['fields'] ?? [];

            // Test d'extraction avec les bons noms de champs
            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
            $offContractEquipments = $fields['tableau2']['value'] ?? [];

            $output->writeln("📊 Équipements trouvés :");
            $output->writeln("   • Sous contrat: " . count($contractEquipments));
            $output->writeln("   • Hors contrat: " . count($offContractEquipments));

            // Test des informations générales avec les bons champs
            $infosGenerales = [
                'id_societe' => $fields['id_societe']['value'] ?? 'N/A',
                'nom_client' => $fields['nom_client']['value'] ?? 'N/A',
                'code_agence' => $fields['code_agence']['value'] ?? 'N/A',
                'trigramme' => $fields['trigramme']['value'] ?? 'N/A',
                'date_et_heure1' => $fields['date_et_heure1']['value'] ?? 'N/A',
            ];

            $output->writeln("\n📋 Informations générales extraites :");
            foreach ($infosGenerales as $key => $value) {
                $output->writeln("   • {$key}: {$value}");
            }

            // Analyser la structure du premier équipement sous contrat
            if (!empty($contractEquipments)) {
                $output->writeln("\n🔍 Analyse du premier équipement sous contrat :");
                $firstEquipment = $contractEquipments[0];
                
                // Champs essentiels pour l'extraction
                $essentialFields = [
                    'equipement',
                    'mode_fonctionnement_2',
                    'localisation_site_client',
                    'etat'
                ];

                foreach ($essentialFields as $field) {
                    if (isset($firstEquipment[$field])) {
                        $fieldData = $firstEquipment[$field];
                        if (is_array($fieldData) && isset($fieldData['value'])) {
                            $value = is_array($fieldData['value']) ? 
                                '[Array avec ' . count($fieldData['value']) . ' éléments]' : 
                                substr($fieldData['value'], 0, 50) . (strlen($fieldData['value']) > 50 ? '...' : '');
                            $output->writeln("   ✅ {$field}: {$value}");
                        } else {
                            $output->writeln("   ❌ {$field}: Structure inattendue");
                        }
                    } else {
                        $output->writeln("   ❌ {$field}: Champ manquant");
                    }
                }

                // Test d'extraction des infos de l'équipement
                if (isset($firstEquipment['equipement']['value'])) {
                    $equipementValue = $firstEquipment['equipement']['value'];
                    $output->writeln("\n📝 Valeur du champ 'equipement' :");
                    $output->writeln("   " . substr($equipementValue, 0, 200) . (strlen($equipementValue) > 200 ? '...' : ''));
                    
                    // Essayer de parser les infos équipement
                    $output->writeln("\n🔧 Test de parsing des infos équipement :");
                    $this->testParseEquipmentInfo($equipementValue, $output);
                }
            }

            // Suggérer les corrections nécessaires
            $output->writeln("\n💡 Corrections nécessaires dans le code :");
            $output->writeln("   1. Changer 'nom_du_client' → 'nom_client'");
            $output->writeln("   2. Changer 'technicien' → 'trigramme'");
            $output->writeln("   3. Changer 'date_et_heure' → 'date_et_heure1'");
            $output->writeln("   4. Vérifier la logique d'extraction dans processSingleSubmissionWithDeduplication");

        } catch (\Exception $e) {
            $output->writeln("<e>Erreur: " . $e->getMessage() . "</e>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function testParseEquipmentInfo(string $equipementValue, $output): void
    {
        // Test de parsing basique
        if (strpos($equipementValue, '|') !== false) {
            $parts = explode('|', $equipementValue);
            $output->writeln("   • Parsing par '|': " . count($parts) . " parties");
            for ($i = 0; $i < min(3, count($parts)); $i++) {
                $output->writeln("     [{$i}]: " . trim($parts[$i]));
            }
        } elseif (strpos($equipementValue, '-') !== false) {
            $parts = explode('-', $equipementValue);
            $output->writeln("   • Parsing par '-': " . count($parts) . " parties");
            for ($i = 0; $i < min(3, count($parts)); $i++) {
                $output->writeln("     [{$i}]: " . trim($parts[$i]));
            }
        } else {
            $output->writeln("   • Format non reconnu, valeur brute");
        }
    }
}