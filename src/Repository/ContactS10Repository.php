<?php

namespace App\Repository;

use App\Entity\ContactS10;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactS10>
 */
class ContactS10Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactS10::class);
    }

   /**
    * @return ContactS10[] Returns an array of ContactS10 objects
    */
   public function findByExampleField($value): array
   {
       return $this->createQueryBuilder('c')
           ->andWhere('c.exampleField = :val')
           ->setParameter('val', $value)
           ->orderBy('c.id', 'ASC')
           ->setMaxResults(10)
           ->getQuery()
           ->getResult()
       ;
   }

   public function findOneBySomeField($value): ?ContactS10
   {
       return $this->createQueryBuilder('c')
           ->andWhere('c.exampleField = :val')
           ->setParameter('val', $value)
           ->getQuery()
           ->getOneOrNullResult()
       ;
   }
}
