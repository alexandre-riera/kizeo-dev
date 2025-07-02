<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
/**
 * Commande pour tester la déduplication des équipements S50.
 */
#[AsCommand(
    name: 'app:test-s50-deduplication',
    description: 'Test deduplication S50'
)]
class TestS50DeduplicationCommand extends Command
{
    protected static $defaultName = 'app:test-s50-deduplication';

    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Test de déduplication pour S50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("🔍 Test de déduplication S50");

        try {
            $entityClass = 'App\\Entity\\EquipementS50';
            
            // Données du diagnostic
            $numeroEquipement = 'RAP01';
            $idClient = '5696';
            $dateVisite = '2025-07-02';

            $output->writeln("📋 Test avec :");
            $output->writeln("   • Équipement: {$numeroEquipement}");
            $output->writeln("   • Client: {$idClient}");
            $output->writeln("   • Date: {$dateVisite}");

            // Test 1: Équipement existe-t-il ?
            $repository = $this->entityManager->getRepository($entityClass);
            
            $existing = $repository->createQueryBuilder('e')
                ->where('e.numero_equipement = :numero')
                ->andWhere('e.id_contact = :idClient')
                ->setParameter('numero', $numeroEquipement)
                ->setParameter('idClient', $idClient)
                ->getQuery()
                ->getResult();

            $output->writeln("\n🔍 Test 1 - Équipement existe (sans date) :");
            $output->writeln("   Résultats trouvés: " . count($existing));
            
            if (!empty($existing)) {
                foreach ($existing as $equip) {
                    $lastDate = $equip->getDateEnregistrement() ?? 'NULL';
                    $output->writeln("   • ID: " . $equip->getId() . " - Date: {$lastDate}");
                }
            }

            // Test 2: Équipement existe pour la même date ?
            $existingSameDate = $repository->createQueryBuilder('e')
                ->where('e.numero_equipement = :numero')
                ->andWhere('e.id_contact = :idClient')
                ->andWhere('e.date_enregistrement = :dateVisite')
                ->setParameter('numero', $numeroEquipement)
                ->setParameter('idClient', $idClient)
                ->setParameter('dateVisite', $dateVisite)
                ->getQuery()
                ->getResult();

            $output->writeln("\n🔍 Test 2 - Équipement existe pour MÊME DATE :");
            $output->writeln("   Résultats trouvés: " . count($existingSameDate));

            // Test 3: Tous les équipements de ce client aujourd'hui
            $allToday = $repository->createQueryBuilder('e')
                ->where('e.id_contact = :idClient')
                ->andWhere('e.date_enregistrement = :dateVisite')
                ->setParameter('idClient', $idClient)
                ->setParameter('dateVisite', $dateVisite)
                ->getQuery()
                ->getResult();

            $output->writeln("\n🔍 Test 3 - Tous les équipements de ce client aujourd'hui :");
            $output->writeln("   Résultats trouvés: " . count($allToday));
            
            if (!empty($allToday)) {
                foreach ($allToday as $equip) {
                    $numero = $equip->getNumeroEquipement();
                    $libelle = $equip->getLibelleEquipement();
                    $output->writeln("   • {$numero} - {$libelle}");
                }
            }

            // Diagnostic
            $output->writeln("\n💡 DIAGNOSTIC :");
            $output->writeln("================");

            if (count($existingSameDate) > 0) {
                $output->writeln("🚨 PROBLÈME TROUVÉ: L'équipement RAP01 existe déjà pour la date d'aujourd'hui");
                $output->writeln("✅ SOLUTION: La déduplication fonctionne correctement et skip les doublons");
                $output->writeln("💡 EXPLICATION: Ces équipements ont déjà été traités aujourd'hui");
            } else if (count($existing) > 0) {
                $output->writeln("✅ L'équipement existe mais pas pour aujourd'hui");
                $output->writeln("🔍 Il devrait donc être traité... problème ailleurs");
            } else {
                $output->writeln("✅ L'équipement n'existe pas du tout");
                $output->writeln("🔍 Il devrait être traité... problème dans la logique");
            }

        } catch (\Exception $e) {
            $output->writeln("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}