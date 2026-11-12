<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UploadLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UploadLink>
 */
class UploadLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UploadLink::class);
    }

    public function findOneByToken(string $token): ?UploadLink
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Counts one more file of this size against the limits of the link, in a single statement so that
     * concurrent uploads cannot go over them together.
     *
     * @return bool false when the link is no longer usable or the file would go over a limit
     */
    public function reserve(string $id, int $size, \DateTimeImmutable $now): bool
    {
        return 1 === $this->getEntityManager()->getConnection()->executeStatement(<<<'SQL'
            UPDATE upload_link
            SET used_assets = used_assets + 1, used_size = used_size + :size
            WHERE id = :id
              AND enabled = :enabled
              AND (expires_at IS NULL OR expires_at > :now)
              AND (max_assets IS NULL OR used_assets + 1 <= max_assets)
              AND (max_total_size IS NULL OR used_size + :size <= max_total_size)
            SQL, [
            'id' => $id,
            'size' => $size,
            'enabled' => true,
            'now' => $now,
        ], [
            'size' => ParameterType::INTEGER,
            'enabled' => ParameterType::BOOLEAN,
            'now' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    /**
     * Gives back what a file not committed yet had reserved.
     */
    public function release(string $id, int $size): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(<<<'SQL'
            UPDATE upload_link
            SET used_assets = CASE WHEN used_assets > 0 THEN used_assets - 1 ELSE 0 END,
                used_size = CASE WHEN used_size > :size THEN used_size - :size ELSE 0 END
            WHERE id = :id
            SQL, [
            'id' => $id,
            'size' => $size,
        ], [
            'size' => ParameterType::INTEGER,
        ]);
    }
}
