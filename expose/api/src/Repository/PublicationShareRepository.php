<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\PublicationShare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicationShare>
 */
class PublicationShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationShare::class);
    }

    /**
     * @param string[] $tokens
     *
     * @return PublicationShare[] the shares of these tokens neither revoked nor expired
     */
    public function findActiveByTokens(array $tokens): array
    {
        if ([] === $tokens) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.token IN (:tokens)')
            ->andWhere('s.revokedAt IS NULL')
            ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :now')
            ->setParameter('tokens', array_values($tokens))
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PublicationShare[]
     */
    public function findByPublication(Publication $publication): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.publication = :publication')
            ->setParameter('publication', $publication->getId())
            ->addOrderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
