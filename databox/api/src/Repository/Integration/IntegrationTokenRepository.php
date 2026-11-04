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

    /**
     * Tokens whose refresh token is still valid but expires within $threshold seconds:
     * renewing them now extends their lifetime.
     *
     * @return IntegrationToken[]
     */
    public function getRenewableTokens(int $threshold): array
    {
        $now = new \DateTimeImmutable();

        return $this
            ->createQueryBuilder('it')
            ->andWhere('it.hasRefreshToken = true')
            ->andWhere('it.expiresAt > :now')
            ->andWhere('it.expiresAt <= :limit')
            ->setParameter('now', $now)
            ->setParameter('limit', $now->modify(sprintf('+%d seconds', $threshold)))
            ->getQuery()
            ->getResult();
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
