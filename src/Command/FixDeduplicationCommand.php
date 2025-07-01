<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\HttpClient\HttpClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:fix-deduplication',
    description: 'Test et correction de la logique de déduplication',
)]
class FixDeduplicationCommand extends Command
{
    protected static $defaultName = 'app:fix-deduplication';

    private $client;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->client = HttpClient::create();
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->setDescription('Test et correction de la logique de déduplication')
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S100)')
            ->addOption('force-update', null, InputOption::VALUE_NONE, 'Forcer la mise à jour même si existe')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agencyCode = $input->getArgument('agency');
        $forceUpdate = $input->getOption('force-update');
        
        // Form ID pour S100
        $formId = '1071913';
        $entryId = '234979977'; // Celui du debug

        $output->writeln("🔧 Test de la logique de déduplication pour {$agencyCode}");

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

            // Test de déduplication
            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
            
            if (empty($contractEquipments)) {
                $output->writeln("<e>Aucun équipement sous contrat trouvé</e>");
                return Command::FAILURE;
            }

            $entityClass = 'App\\Entity\\EquipementS100';
            $idClient = $fields['id_client_']['value'] ?? '';

            $output->writeln("📊 Test de déduplication sur " . count($contractEquipments) . " équipements");
            $output->writeln("   • ID Client: {$idClient}");
            $output->writeln("   • Entity Class: {$entityClass}");

            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($contractEquipments as $index => $equipmentContrat) {
                $numeroEquipement = $equipmentContrat['equipement']['value'] ?? '';
                
                $output->writeln("\n🔍 Équipement " . ($index + 1) . ": {$numeroEquipement}");
                
                // Test si l'équipement existe
                $exists = $this->equipmentExists($numeroEquipement, $idClient, $entityClass);
                
                if ($exists) {
                    $output->writeln("   ❌ Équipement existe déjà en base - serait SKIPPÉ");
                    
                    if ($forceUpdate) {
                        $output->writeln("   🔄 Force update activé - traitement quand même");
                        $result = $this->processEquipmentWithUpdate($equipmentContrat, $fields, $entityClass, $numeroEquipement, $idClient);
                        if ($result) {
                            $processedCount++;
                            $output->writeln("   ✅ Équipement mis à jour");
                        } else {
                            $errorCount++;
                            $output->writeln("   ❌ Erreur lors de la mise à jour");
                        }
                    } else {
                        $skippedCount++;
                    }
                } else {
                    $output->writeln("   ✅ Équipement n'existe pas - serait TRAITÉ");
                    $processedCount++;
                }
                
                // Afficher quelques infos sur l'équipement existant
                if ($exists) {
                    $existingEquipment = $this->getExistingEquipment($numeroEquipement, $idClient, $entityClass);
                    if ($existingEquipment) {
                        $lastUpdate = $existingEquipment->getDateEnregistrement() ?? 'Inconnue';
                        $output->writeln("   📅 Dernière mise à jour: {$lastUpdate}");
                        $output->writeln("   📋 Libellé actuel: " . ($existingEquipment->getLibelleEquipement() ?? 'N/A'));
                    }
                }
            }

            // Résumé
            $output->writeln("\n" . str_repeat('=', 50));
            $output->writeln("📈 Résumé du test de déduplication:");
            $output->writeln("   ✅ Équipements qui seraient traités: <info>{$processedCount}</info>");
            $output->writeln("   ❌ Équipements qui seraient skippés: <e>{$skippedCount}</e>");
            $output->writeln("   🔥 Erreurs: <comment>{$errorCount}</comment>");

            // Recommandations
            $output->writeln("\n💡 Recommandations:");
            
            if ($skippedCount > 0) {
                $output->writeln("   1. La déduplication fonctionne et skip {$skippedCount} équipements existants");
                $output->writeln("   2. Pour traiter quand même, ajoutez --force-update");
                $output->writeln("   3. Ou modifiez la logique pour faire des mises à jour au lieu de skip");
                $output->writeln("   4. Ou videz la base pour permettre les nouveaux ajouts");
            }
            
            if ($processedCount === 0 && $skippedCount > 0) {
                $output->writeln("   🚨 PROBLÈME IDENTIFIÉ: Tous les équipements sont skippés !");
                $output->writeln("   🛠️  SOLUTION: Modifier setRealContractDataWithFormPhotosAndDeduplication()");
                $output->writeln("      pour faire UPDATE au lieu de SKIP");
            }

        } catch (\Exception $e) {
            $output->writeln("<e>Erreur: " . $e->getMessage() . "</e>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
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

    private function getExistingEquipment(string $numeroEquipement, string $idClient, string $entityClass)
    {
        try {
            $repository = $this->entityManager->getRepository($entityClass);
            
            return $repository->createQueryBuilder('e')
                ->where('e.numero_equipement = :numero')
                ->andWhere('e.id_contact = :idClient')
                ->setParameter('numero', $numeroEquipement)
                ->setParameter('idClient', $idClient)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
        } catch (\Exception $e) {
            return null;
        }
    }

    private function processEquipmentWithUpdate(array $equipmentContrat, array $fields, string $entityClass, string $numeroEquipement, string $idClient): bool
    {
        try {
            // Récupérer l'équipement existant
            $repository = $this->entityManager->getRepository($entityClass);
            $equipement = $repository->createQueryBuilder('e')
                ->where('e.numero_equipement = :numero')
                ->andWhere('e.id_contact = :idClient')
                ->setParameter('numero', $numeroEquipement)
                ->setParameter('idClient', $idClient)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$equipement) {
                return false;
            }

            // Mettre à jour avec les nouvelles données
            $equipement->setDateEnregistrement($fields['date_et_heure1']['value'] ?? '');
            $equipement->setTrigrammeTech($fields['trigramme']['value'] ?? '');
            
            // Mettre à jour l'état et le statut
            $etat = $equipmentContrat['etat']['value'] ?? '';
            $equipement->setEtat($etat);
            
            $statut = $this->getMaintenanceStatusFromEtatFixed($etat);
            $equipement->setStatutDeMaintenance($statut);

            $this->entityManager->flush();
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
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
}