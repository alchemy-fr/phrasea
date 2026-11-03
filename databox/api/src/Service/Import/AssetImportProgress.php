<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Core\AssetImport;
use App\Model\AssetImportStatusEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Race-safe accounting of an import whose entries are processed by concurrent workers:
 * every mutation is an atomic SQL UPDATE, so that no count is lost and the import
 * completes exactly once.
 */
final readonly class AssetImportProgress
{
    public const string CREATED = 'created';
    public const string SKIPPED = 'skipped';
    public const string FAILED = 'failed';

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Start reading the file: the counters are reset and the total stays unknown
     * until every entry is dispatched (prevents a premature completion).
     */
    public function startDispatch(string $importId): bool
    {
        return $this->em->createQuery(sprintf(
            'UPDATE %s t SET t.status = :dispatching, t.total = NULL, t.created = 0, t.skipped = 0, t.failed = 0, t.error = NULL, t.updatedAt = :now
             WHERE t.id = :id AND t.status = :pending',
            AssetImport::class
        ))
            ->setParameter('dispatching', AssetImportStatusEnum::Dispatching->value)
            ->setParameter('pending', AssetImportStatusEnum::Pending->value)
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $importId)
            ->execute() > 0;
    }

    public function endDispatch(string $importId, int $total): void
    {
        $this->em->createQuery(sprintf(
            'UPDATE %s t SET t.status = :processing, t.total = :total, t.updatedAt = :now WHERE t.id = :id AND t.status = :dispatching',
            AssetImport::class
        ))
            ->setParameter('processing', AssetImportStatusEnum::Processing->value)
            ->setParameter('dispatching', AssetImportStatusEnum::Dispatching->value)
            ->setParameter('total', $total)
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $importId)
            ->execute();
    }

    /**
     * @param self::CREATED|self::SKIPPED|self::FAILED $counter
     */
    public function increment(string $importId, string $counter): void
    {
        if (!in_array($counter, [self::CREATED, self::SKIPPED, self::FAILED], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid counter "%s"', $counter));
        }

        $this->em->createQuery(sprintf(
            'UPDATE %1$s t SET t.%2$s = t.%2$s + 1, t.updatedAt = :now WHERE t.id = :id',
            AssetImport::class,
            $counter
        ))
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $importId)
            ->execute();
    }

    /**
     * Atomically complete the import iff every entry has been processed. The status
     * guard makes this exactly-once even when several workers end at the same time.
     */
    public function finalizeIfComplete(string $importId): void
    {
        $now = new \DateTimeImmutable();

        $this->em->createQuery(sprintf(
            'UPDATE %s t SET t.status = :completed, t.endedAt = :now, t.updatedAt = :now
             WHERE t.id = :id
               AND t.status = :processing
               AND t.total IS NOT NULL
               AND t.created + t.skipped + t.failed >= t.total',
            AssetImport::class
        ))
            ->setParameter('completed', AssetImportStatusEnum::Completed->value)
            ->setParameter('processing', AssetImportStatusEnum::Processing->value)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $importId)
            ->execute();
    }

    public function fail(string $importId, string $error): void
    {
        $now = new \DateTimeImmutable();

        $this->em->createQuery(sprintf(
            'UPDATE %s t SET t.status = :failed, t.error = :error, t.endedAt = :now, t.updatedAt = :now WHERE t.id = :id',
            AssetImport::class
        ))
            ->setParameter('failed', AssetImportStatusEnum::Failed->value)
            ->setParameter('error', $error)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $importId)
            ->execute();
    }
}
