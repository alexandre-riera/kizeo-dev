<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\HttpClient\HttpClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:enhanced-debug',
    description: 'Debug avancé avec simulation complète du traitement',
)]
class EnhancedDebugCommand extends Command
{
    protected static $defaultName = 'app:enhanced-debug';

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
            ->setDescription('Debug avancé avec simulation complète du traitement')
            ->addArgument('agency', InputArgument::REQUIRED, 'Code agence (S100)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agencyCode = $input->getArgument('agency');
        
        // Form ID pour S100
        $formId = '1071913';
        $entryId = '234979977';

        $output->writeln("🔍 Debug avancé du traitement complet pour {$agencyCode}");

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

            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
            $entityClass = 'App\\Entity\\EquipementS100';

            $output->writeln("📊 Simulation complète du traitement");

            // === SIMULATION ÉTAPE PAR ÉTAPE ===

            foreach ($contractEquipments as $index => $equipmentContrat) {
                $output->writeln("\n" . str_repeat('=', 60));
                $output->writeln("🔧 SIMULATION ÉQUIPEMENT " . ($index + 1) . "/" . count($contractEquipments));
                $output->writeln(str_repeat('=', 60));

                $numeroEquipement = $equipmentContrat['equipement']['value'] ?? '';
                $output->writeln("📋 Numéro équipement: {$numeroEquipement}");

                // ÉTAPE 1: Création de l'objet
                $output->writeln("\n1️⃣ Création de l'objet équipement...");
                try {
                    $equipement = new $entityClass();
                    $output->writeln("   ✅ Objet créé avec succès");
                } catch (\Exception $e) {
                    $output->writeln("   ❌ Erreur création objet: " . $e->getMessage());
                    continue;
                }

                // ÉTAPE 2: Données communes
                $output->writeln("\n2️⃣ Application des données communes...");
                try {
                    $this->setRealCommonDataFixed($equipement, $fields);
                    $output->writeln("   ✅ Données communes appliquées");
                    $output->writeln("   📋 Code agence: " . ($equipement->getCodeAgence() ?? 'NULL'));
                    $output->writeln("   📋 ID Contact: " . ($equipement->getIdContact() ?? 'NULL'));
                    $output->writeln("   📋 Raison sociale: " . ($equipement->getRaisonSociale() ?? 'NULL'));
                } catch (\Exception $e) {
                    $output->writeln("   ❌ Erreur données communes: " . $e->getMessage());
                    continue;
                }

                // ÉTAPE 3: Déduplication
                $output->writeln("\n3️⃣ Test de déduplication...");
                $idClient = $fields['id_client_']['value'] ?? '';
                $exists = $this->equipmentExists($numeroEquipement, $idClient, $entityClass);
                if ($exists) {
                    $output->writeln("   ❌ Équipement existe déjà - serait skippé");
                    continue;
                } else {
                    $output->writeln("   ✅ Équipement n'existe pas - traitement continue");
                }

                // ÉTAPE 4: Données spécifiques contrat
                $output->writeln("\n4️⃣ Application des données spécifiques...");
                try {
                    $this->setRealContractDataFixed($equipement, $equipmentContrat, $fields, $formId, $entryId);
                    $output->writeln("   ✅ Données spécifiques appliquées");
                    $output->writeln("   📋 Numéro: " . ($equipement->getNumeroEquipement() ?? 'NULL'));
                    $output->writeln("   📋 Libellé: " . ($equipement->getLibelleEquipement() ?? 'NULL'));
                    $output->writeln("   📋 État: " . ($equipement->getEtat() ?? 'NULL'));
                    $output->writeln("   📋 En maintenance: " . ($equipement->isEnMaintenance() ? 'true' : 'false'));
                } catch (\Exception $e) {
                    $output->writeln("   ❌ Erreur données spécifiques: " . $e->getMessage());
                    $output->writeln("   🔍 Stack trace: " . $e->getTraceAsString());
                    continue;
                }

                // ÉTAPE 5: Validation avant persist
                $output->writeln("\n5️⃣ Validation avant persist...");
                $validationErrors = $this->validateEquipment($equipement);
                if (!empty($validationErrors)) {
                    $output->writeln("   ❌ Erreurs de validation:");
                    foreach ($validationErrors as $error) {
                        $output->writeln("      • " . $error);
                    }
                    continue;
                } else {
                    $output->writeln("   ✅ Validation réussie");
                }

                // ÉTAPE 6: Test de persistance (simulation)
                $output->writeln("\n6️⃣ Test de persistance...");
                try {
                    // Simulation persist/flush
                    $this->entityManager->persist($equipement);
                    $output->writeln("   ✅ Persist() réussi");
                    
                    // Test flush (dans une transaction pour rollback)
                    $this->entityManager->getConnection()->beginTransaction();
                    $this->entityManager->flush();
                    $output->writeln("   ✅ Flush() réussi");
                    
                    // Rollback pour ne pas vraiment enregistrer
                    $this->entityManager->getConnection()->rollback();
                    $this->entityManager->clear();
                    $output->writeln("   🔄 Transaction rollback (test seulement)");
                    
                } catch (\Exception $e) {
                    $output->writeln("   ❌ Erreur persistance: " . $e->getMessage());
                    $output->writeln("   🔍 Stack trace: " . $e->getTraceAsString());
                    
                    // Cleanup en cas d'erreur
                    try {
                        $this->entityManager->getConnection()->rollback();
                    } catch (\Exception $rollbackError) {
                        // Ignore rollback errors
                    }
                    $this->entityManager->clear();
                    continue;
                }

                $output->writeln("\n✅ ÉQUIPEMENT " . ($index + 1) . " TRAITÉ AVEC SUCCÈS");
            }

            $output->writeln("\n" . str_repeat('=', 60));
            $output->writeln("📈 RÉSULTAT DU DEBUG AVANCÉ");
            $output->writeln(str_repeat('=', 60));
            $output->writeln("🔍 Si ce test montre que tout fonctionne,");
            $output->writeln("   alors le problème est dans la méthode");
            $output->writeln("   setRealContractDataWithFormPhotosAndDeduplication()");
            $output->writeln("   qui n'utilise pas la bonne logique.");

        } catch (\Exception $e) {
            $output->writeln("<e>Erreur générale: " . $e->getMessage() . "</e>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
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

    private function setRealContractDataFixed($equipement, array $equipmentContrat, array $fields, string $formId, string $entryId): void
    {
        // Type de visite depuis le path
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        // Numéro d'équipement
        $numeroEquipement = $equipmentContrat['equipement']['value'] ?? '';
        $equipement->setNumeroEquipement($numeroEquipement);
        
        // Données supplémentaires nécessaires
        $equipement->setCodeSociete($fields['id_societe']['value'] ?? '');
        $equipement->setDerniereVisite($fields['date_et_heure1']['value'] ?? '');
        $equipement->setTest($fields['test_']['value'] ?? '');
        
        // Libellé depuis reference7
        $libelle = $equipmentContrat['reference7']['value'] ?? '';
        $equipement->setLibelleEquipement($libelle);
        
        // Autres données
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

    private function validateEquipment($equipement): array
    {
        $errors = [];
        
        if (empty($equipement->getNumeroEquipement())) {
            $errors[] = "Numéro d'équipement manquant";
        }
        
        if (empty($equipement->getCodeAgence())) {
            $errors[] = "Code agence manquant";
        }
        
        if (empty($equipement->getIdContact())) {
            $errors[] = "ID Contact manquant";
        }
        
        return $errors;
    }
}