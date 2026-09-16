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
     * Tokens still refreshable (their refresh token is not expired yet) whose
     * access token expires within $threshold seconds.
     *
     * @param int $threshold Seconds before the access token expiry from which a renewal is due
     *
     * @return IntegrationToken[]
     */
    public function getRenewableTokens(int $threshold = 0): array
    {
        $tokens = $this
            ->createQueryBuilder('it')
            ->andWhere('it.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();

        // The access token expiry lives in the JSON column: filter in PHP.
        return array_values(array_filter(
            $tokens,
            fn (IntegrationToken $token): bool => $token->isRenewalDue($threshold),
        ));
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
