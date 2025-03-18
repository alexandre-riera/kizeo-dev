<?php

namespace App\Repository;

use App\Entity\ContratS150;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContratS150>
 */
class ContratRepositoryS150 extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContratS150::class);
    }

//    /**
//     * @return ContratS150[] Returns an array of ContratS150 objects
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

   public function findContratByIdContact($idContact): ?ContratS150
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
            "CE1",
            "CE2",
            "CE3",
            "CE4",
        ];
        return $visites; 
   }
}
