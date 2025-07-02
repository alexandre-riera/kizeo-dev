<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:debug-s50-structure',
    description: 'Debug de la structure des formulaires S50'
)]

class DebugS50StructureCommand extends Command
{
    protected static $defaultName = 'app:debug-s50-structure';

    private $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = HttpClient::create();
    }

    protected function configure()
    {
        $this->setDescription('Debug de la structure des formulaires S50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("🔍 Debug de la structure S50 vs S100");

        try {
            // Form IDs
            $formS50 = '1065302'; // V5 Grenoble
            $formS100 = '1071913'; // V5 Montpellier

            $output->writeln("\n📋 Récupération des soumissions récentes...");

            // Récupérer une soumission récente S50
            $responseS50 = $this->client->request('GET', 
                "https://forms.kizeo.com/rest/v3/forms/{$formS50}/data/all?limit=1",
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $dataS50 = $responseS50->toArray();
            
            if (empty($dataS50['data'])) {
                $output->writeln("❌ Aucune soumission trouvée pour S50");
                return Command::FAILURE;
            }

            $entryS50 = $dataS50['data'][0]['id'];
            $output->writeln("📝 Soumission S50 trouvée: {$entryS50}");

            // Récupérer les détails S50
            $detailS50 = $this->client->request('GET',
                "https://forms.kizeo.com/rest/v3/forms/{$formS50}/data/{$entryS50}",
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $fieldsS50 = $detailS50->toArray()['data']['fields'] ?? [];

            $output->writeln("\n🔍 COMPARAISON DES STRUCTURES :");
            $output->writeln("=====================================");

            // Comparer les champs critiques
            $criticalFields = [
                'contrat_de_maintenance',
                'tableau2', 
                'hors_contrat',
                'equipements',
                'nom_client',
                'nom_du_client',
                'id_client_',
                'code_agence',
                'trigramme',
                'technicien',
                'date_et_heure1',
                'date_et_heure'
            ];

            foreach ($criticalFields as $field) {
                if (isset($fieldsS50[$field])) {
                    $value = $fieldsS50[$field]['value'] ?? $fieldsS50[$field];
                    if (is_array($value)) {
                        $count = count($value);
                        $output->writeln("✅ S50 - {$field}: [Array avec {$count} éléments]");
                    } else {
                        $preview = strlen($value) > 30 ? substr($value, 0, 30) . '...' : $value;
                        $output->writeln("✅ S50 - {$field}: {$preview}");
                    }
                } else {
                    $output->writeln("❌ S50 - {$field}: MANQUANT");
                }
            }

            // Analyser spécifiquement les équipements S50
            $output->writeln("\n🔧 ANALYSE DES ÉQUIPEMENTS S50 :");
            $output->writeln("================================");

            $contractEquipments = $fieldsS50['contrat_de_maintenance']['value'] ?? [];
            $offContractEquipments = $fieldsS50['tableau2']['value'] ?? [];

            $output->writeln("📊 Équipements sous contrat: " . count($contractEquipments));
            $output->writeln("📊 Équipements hors contrat: " . count($offContractEquipments));

            if (!empty($contractEquipments)) {
                $output->writeln("\n🔍 Structure du premier équipement sous contrat S50 :");
                $firstEquip = $contractEquipments[0];
                
                $essentialFields = [
                    'equipement',
                    'reference7',
                    'reference2', 
                    'reference6',
                    'reference5',
                    'reference1',
                    'reference3',
                    'localisation_site_client',
                    'mode_fonctionnement_2',
                    'etat'
                ];

                foreach ($essentialFields as $field) {
                    if (isset($firstEquip[$field])) {
                        $fieldData = $firstEquip[$field];
                        if (is_array($fieldData) && isset($fieldData['value'])) {
                            $value = $fieldData['value'];
                            $output->writeln("   ✅ {$field}: {$value}");
                        } else {
                            $output->writeln("   ❌ {$field}: Structure inattendue");
                        }
                    } else {
                        $output->writeln("   ❌ {$field}: MANQUANT");
                    }
                }

                // Vérifier le path
                if (isset($firstEquip['equipement']['path'])) {
                    $path = $firstEquip['equipement']['path'];
                    $output->writeln("   📍 Path: {$path}");
                } else {
                    $output->writeln("   ❌ Path: MANQUANT");
                }
            }

            // Recommandations
            $output->writeln("\n💡 DIAGNOSTIC :");
            $output->writeln("================");
            
            if (empty($contractEquipments) && empty($offContractEquipments)) {
                $output->writeln("🚨 PROBLÈME: Aucun équipement trouvé dans les champs attendus");
                $output->writeln("🔍 Solution: Vérifier si S50 utilise d'autres noms de champs");
                
                // Lister tous les champs de type array
                $output->writeln("\n📋 Tous les champs array dans S50 :");
                foreach ($fieldsS50 as $fieldName => $fieldData) {
                    if (isset($fieldData['value']) && is_array($fieldData['value']) && !empty($fieldData['value'])) {
                        $count = count($fieldData['value']);
                        $output->writeln("   • {$fieldName}: [{$count} éléments]");
                    }
                }
            } else {
                $output->writeln("✅ Équipements trouvés, vérifier la logique d'extraction");
            }

        } catch (\Exception $e) {
            $output->writeln("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}