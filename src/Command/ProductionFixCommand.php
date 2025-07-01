<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use App\Controller\SimplifiedMaintenanceController;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\MaintenanceCacheService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:production-fix',
    description: 'CORRECTION PRODUCTION : Traitement RÉEL sans rollback',
)]
class ProductionFixCommand extends Command
{
    protected static $defaultName = 'app:production-fix';

    public function __construct(
        private SimplifiedMaintenanceController $maintenanceController,
        private EntityManagerInterface $entityManager,
        private MaintenanceCacheService $cacheService
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('CORRECTION PRODUCTION : Traitement RÉEL sans rollback')
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S100)')
            ->addOption('entry-id', null, InputOption::VALUE_OPTIONAL, 'ID de soumission spécifique')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Confirmer l\'enregistrement en production')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agencyCode = $input->getArgument('agency');
        $entryId = $input->getOption('entry-id');
        $confirm = $input->getOption('confirm');
        
        if (!$confirm) {
            $output->writeln("⚠️  ATTENTION: Ce traitement va RÉELLEMENT enregistrer en base !");
            $output->writeln("   Ajoutez --confirm pour procéder");
            return Command::FAILURE;
        }

        $output->writeln("🚀 TRAITEMENT PRODUCTION pour {$agencyCode}");
        $output->writeln("⚠️  Mode RÉEL - Les données seront ENREGISTRÉES !");

        try {
            // Si pas d'entry_id spécifique, utiliser celui du debug
            if (!$entryId) {
                $entryId = '234979977'; // Celui qu'on a testé
            }

            // Utiliser l'endpoint simple qui fonctionne
            $request = new Request();
            $request->query->set('entry_id', $entryId);
            
            // Appeler directement l'endpoint simple S140 modifié pour S100
            $result = $this->processSpecificEntry($agencyCode, $entryId);
            
            if ($result['success']) {
                $output->writeln("✅ SUCCÈS: " . $result['equipment_count'] . " équipements enregistrés");
                foreach ($result['equipments'] as $equipment) {
                    $output->writeln("   • " . $equipment['numero'] . " - " . $equipment['libelle']);
                }
            } else {
                $output->writeln("❌ ÉCHEC: " . $result['error']);
            }

        } catch (\Exception $e) {
            $output->writeln("❌ ERREUR: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function processSpecificEntry(string $agencyCode, string $entryId): array
    {
        // Mapping pour S100
        $formId = '1071913';
        $entityClass = 'App\\Entity\\EquipementS100';
        
        try {
            // Récupérer les données
            $client = \Symfony\Component\HttpClient\HttpClient::create();
            $detailResponse = $client->request('GET',
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/data/{$entryId}",
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            $fields = $detailData['data']['fields'];

            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
            $processedEquipments = [];
            $equipmentCount = 0;

            // Traiter chaque équipement SANS rollback
            foreach ($contractEquipments as $equipmentContrat) {
                $equipement = new $entityClass();
                
                // Données communes
                $this->setRealCommonDataFixed($equipement, $fields);
                
                // Données spécifiques SANS logique de test
                $this->setRealContractDataProduction($equipement, $equipmentContrat, $fields);
                
                // Vérifier déduplication
                $numeroEquipement = $equipement->getNumeroEquipement();
                $idClient = $equipement->getIdContact();
                
                if (!$this->equipmentExists($numeroEquipement, $idClient, $entityClass)) {
                    // ENREGISTREMENT RÉEL - PAS DE ROLLBACK
                    $this->entityManager->persist($equipement);
                    $this->entityManager->flush(); // COMMIT RÉEL
                    
                    $processedEquipments[] = [
                        'numero' => $numeroEquipement,
                        'libelle' => $equipement->getLibelleEquipement(),
                        'etat' => $equipement->getEtat()
                    ];
                    $equipmentCount++;
                }
            }

            return [
                'success' => true,
                'equipment_count' => $equipmentCount,
                'equipments' => $processedEquipments
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function setRealCommonDataFixed($equipement, array $fields): void
    {
        $equipement->setCodeAgence($fields['code_agence']['value'] ?? '');
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_client']['value'] ?? '');
        $equipement->setTrigrammeTech($fields['trigramme']['value'] ?? '');
        $equipement->setDateEnregistrement($fields['date_et_heure1']['value'] ?? '');
        
        $equipement->setEtatDesLieuxFait(false);
        $equipement->setIsArchive(false);
    }

    private function setRealContractDataProduction($equipement, array $equipmentContrat, array $fields): void
    {
        // LOGIQUE SIMPLE SANS TRANSACTION DE TEST
        
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        $equipement->setNumeroEquipement($equipmentContrat['equipement']['value'] ?? '');
        $equipement->setCodeSociete($fields['id_societe']['value'] ?? '');
        $equipement->setDerniereVisite($fields['date_et_heure1']['value'] ?? '');
        $equipement->setTest($fields['test_']['value'] ?? '');
        
        $equipement->setLibelleEquipement($equipmentContrat['reference7']['value'] ?? '');
        $equipement->setMiseEnService($equipmentContrat['reference2']['value'] ?? '');
        $equipement->setNumeroDeSerie($equipmentContrat['reference6']['value'] ?? '');
        $equipement->setMarque($equipmentContrat['reference5']['value'] ?? '');
        $equipement->setHauteur($equipmentContrat['reference1']['value'] ?? '');
        $equipement->setLargeur($equipmentContrat['reference3']['value'] ?? '');
        $equipement->setRepereSiteClient($equipmentContrat['localisation_site_client']['value'] ?? '');
        $equipement->setModeFonctionnement($equipmentContrat['mode_fonctionnement_2']['value'] ?? '');
        $equipement->setPlaqueSignaletique($equipmentContrat['plaque_signaletique']['value'] ?? '');
        
        $etat = $equipmentContrat['etat']['value'] ?? '';
        $equipement->setEtat($etat);
        
        $statut = $this->getMaintenanceStatusFromEtatFixed($etat);
        $equipement->setStatutDeMaintenance($statut);
        
        $equipement->setEnMaintenance(true);
    }

    private function extractVisitTypeFromPath(string $path): string
    {
        if (str_contains($path, 'CE1')) return 'CE1';
        if (str_contains($path, 'CE2')) return 'CE2';
        if (str_contains($path, 'CE3')) return 'CE3';
        if (str_contains($path, 'CE4')) return 'CE4';
        if (str_contains($path, 'CEA')) return 'CEA';
        return 'CE1';
    }

    private function getMaintenanceStatusFromEtatFixed(string $etat): string
    {
        switch ($etat) {
            case "F":
            case "B":
                return "RAS";
            case "C":
            case "A":
                return "A_REPARER";
            case "D":
                return "HS";
            default:
                return "RAS";
        }
    }

    private function equipmentExists(string $numeroEquipement, string $idClient, string $entityClass): bool
    {
        try {
            $repository = $this->entityManager->getRepository($entityClass);
            
            $existing = $repository->createQueryBuilder('e')
                ->where('e.numero_equipement = :numero')
                ->andWhere('e.id_contact = :idClient')
                ->setParameter('numero', $numeroEquipement)
                ->setParameter('idClient', $idClient)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            return $existing !== null;
            
        } catch (\Exception $e) {
            return false;
        }
    }
}