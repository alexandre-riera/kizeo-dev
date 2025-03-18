<?php

namespace App\Repository;

use App\Entity\ContratS10;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContratS10>
 */
class ContratRepositoryS10 extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContratS10::class);
    }

//    /**
//     * @return Contrat[] Returns an array of Contrat objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

   public function findContratByIdContact($idContact): ?ContratS10
   {
        $contrat = $this->findOneBy(array('id_contact' => $idContact));
        return $contrat; 
   }
   public function getTypesEquipements()
   {
        $types = [
            "Barrière levante",
            "Bloc roue",
            "Mini-pont",
            "Niveleur",
            "Plaque de quai",
            "Portail",
            "Porte accordéon",
            "Porte coulissante",
            "Porte coupe-feu",
            "Porte frigorifique",
            "Porte piétonne",
            "Porte rapide",
            "Porte sectionnelle",
            "Protection",
            "Rideau métallique",
            "SAS",
            "Table élévatrice",
            "Tourniquet",
            "Volet roulant",
        ];
        return $types; 
   }
   public function getModesFonctionnement()
   {
        $modes = [
            "Manuel",
            "Motorisé",
            "Mixte",
            "Impulsion",
            "Automatique",
            "Hydraulique"
        ];
        return $modes; 
   }
   public function getTypesValorisation()
   {
        $modes = [
            "1%",
            "2%",
            "2,5%",
            "3%",
            "3,5%"
        ];
        return $modes; 
   }
   public function getVisites()
   {
        $visites = [
            "Nécessite 1 visite par an",
            "Nécessite 2 visites par an",
            "Nécessite 3 visites par an",
            "Nécessite 4 visites par an",
        ];
        return $visites; 
   }
}
