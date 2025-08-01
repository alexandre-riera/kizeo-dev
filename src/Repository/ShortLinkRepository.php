<?php

// ===== 3. REPOSITORY POUR LES LIENS COURTS =====

namespace App\Repository;

use App\Entity\ShortLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShortLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShortLink::class);
    }

    public function findByShortCode(string $shortCode): ?ShortLink
    {
        return $this->findOneBy(['shortCode' => $shortCode]);
    }

    public function findActiveByShortCode(string $shortCode): ?ShortLink
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.shortCode = :shortCode')
            ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :now')
            ->setParameter('shortCode', $shortCode)
            ->setParameter('now', new \DateTime())
            ->setMaxResults(1);
        
        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findExpiredLinks(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.expiresAt IS NOT NULL')
            ->andWhere('s.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function cleanupExpiredLinks(): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.expiresAt IS NOT NULL')
            ->andWhere('s.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }
}