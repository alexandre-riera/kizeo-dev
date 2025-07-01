<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:debug-kizeo-submission',
    description: 'Debug une soumission Kizeo spécifique'
)]
class DebugKizeoSubmissionCommand extends Command
{
    protected static $defaultName = 'app:debug-kizeo-submission';

    private $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = HttpClient::create();
    }

    protected function configure()
    {
        $this
            ->setDescription('Debug une soumission Kizeo spécifique')
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S10, S40, etc.)')
            ->addArgument('entry_id', InputArgument::OPTIONAL, 'ID de soumission spécifique')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agencyCode = $input->getArgument('agency');
        $entryId = $input->getArgument('entry_id');

        // Mapping des agences vers form_id
        $agencyMapping = [
            'S10' => '1034808',
            'S40' => '1034818', 
            'S50' => '1034821',
            'S60' => '1034823',
            'S70' => '1034824',
            'S80' => '1034825',
            'S100' => '1071913',
            'S120' => '1034828',
            'S130' => '1034829',
            'S140' => '1088761',
            'S150' => '1034831',
            'S160' => '1034832',
            'S170' => '1034833'
        ];

        $formId = $agencyMapping[$agencyCode] ?? null;
        if (!$formId) {
            $output->writeln("<error>Agence {$agencyCode} non reconnue</error>");
            return Command::FAILURE;
        }

        $output->writeln("🔍 Debug de l'agence <info>{$agencyCode}</info> (Form ID: {$formId})");

        try {
            // 1. Si pas d'entry_id spécifique, récupérer la première soumission
            if (!$entryId) {
                $output->writeln("📋 Récupération de la liste des soumissions...");
                
                $response = $this->client->request('GET', 
                    "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/all?limit=1",
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]
                );

                $listData = $response->toArray();
                
                if (empty($listData['data'])) {
                    $output->writeln("<error>Aucune soumission trouvée pour cette agence</error>");
                    return Command::FAILURE;
                }

                $entryId = $listData['data'][0]['id'];
                $output->writeln("📝 Première soumission trouvée: <comment>{$entryId}</comment>");
            }

            // 2. Récupérer les détails de la soumission
            $output->writeln("🔍 Récupération des détails de la soumission {$entryId}...");
            
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

            $output->writeln("📊 <info>Structure des champs disponibles :</info>");
            foreach ($fields as $fieldName => $fieldData) {
                $type = is_array($fieldData) ? (isset($fieldData['value']) ? gettype($fieldData['value']) : 'unknown') : gettype($fieldData);
                $valuePreview = '';
                
                if (isset($fieldData['value'])) {
                    if (is_array($fieldData['value'])) {
                        $valuePreview = '[Array avec ' . count($fieldData['value']) . ' éléments]';
                    } else {
                        $valuePreview = strlen($fieldData['value']) > 50 ? 
                            substr($fieldData['value'], 0, 50) . '...' : 
                            $fieldData['value'];
                    }
                }
                
                $output->writeln("   • <comment>{$fieldName}</comment> ({$type}): {$valuePreview}");
            }

            // 3. Analyser spécifiquement les champs d'équipements
            $output->writeln("\n🔧 <info>Analyse des champs d'équipements :</info>");
            
            $equipmentFields = [
                'contrat_de_maintenance',
                'tableau2',
                'hors_contrat',
                'equipements',
                'equipement_contrat',
                'equipement_hors_contrat'
            ];

            foreach ($equipmentFields as $fieldName) {
                if (isset($fields[$fieldName])) {
                    $fieldValue = $fields[$fieldName]['value'] ?? $fields[$fieldName];
                    if (is_array($fieldValue)) {
                        $output->writeln("   ✅ <info>{$fieldName}</info>: " . count($fieldValue) . " équipements");
                        
                        // Afficher la structure du premier équipement
                        if (!empty($fieldValue) && is_array($fieldValue[0])) {
                            $output->writeln("      Structure du premier équipement :");
                            foreach ($fieldValue[0] as $key => $value) {
                                $valuePreview = is_array($value) ? '[Array]' : (strlen((string)$value) > 30 ? substr((string)$value, 0, 30) . '...' : (string)$value);
                                $output->writeln("         • {$key}: {$valuePreview}");
                            }
                        }
                    } else {
                        $output->writeln("   ❌ <comment>{$fieldName}</comment>: Non array (" . gettype($fieldValue) . ")");
                    }
                } else {
                    $output->writeln("   ❌ <comment>{$fieldName}</comment>: Champ non trouvé");
                }
            }

            // 4. Afficher les informations générales
            $output->writeln("\n📋 <info>Informations générales :</info>");
            $generalFields = [
                'id_societe',
                'nom_du_client', 
                'code_agence',
                'trigramme',
                'date_et_heure',
                'date_et_heure1'
            ];

            foreach ($generalFields as $fieldName) {
                if (isset($fields[$fieldName])) {
                    $value = $fields[$fieldName]['value'] ?? $fields[$fieldName];
                    $output->writeln("   • <comment>{$fieldName}</comment>: {$value}");
                } else {
                    $output->writeln("   • <comment>{$fieldName}</comment>: Non trouvé");
                }
            }

        } catch (\Exception $e) {
            $output->writeln("<error>Erreur: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}