<?php

declare(strict_types=1);

namespace App\Repository\Integration;

use App\Entity\Integration\IntegrationToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class IntegrationTokenRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, IntegrationToken::class);
    }

    public function getValidUserTokens(string $integrationId, string $userId): array
    {
        return $this->createValidTokenQueryBuilder($integrationId, $userId)
            ->getQuery()
            ->getResult();
    }

    public function getLastValidUserToken(string $integrationId, string $userId): ?IntegrationToken
    {
        return $this->createValidTokenQueryBuilder($integrationId, $userId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createValidTokenQueryBuilder(string $integrationId, string $userId): QueryBuilder
    {
        return $this
            ->createQueryBuilder('it')
            ->andWhere('it.integration = :integration')
            ->andWhere('it.expiresAt > :now')
            ->andWhere('it.userId IS NULL OR it.userId = :uid')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('integration', $integrationId)
            ->setParameter('uid', $userId);
    }
}
