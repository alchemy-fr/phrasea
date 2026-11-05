<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Admin\PopulatePass;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Inspects and force-releases the per-index populate locks taken by PopulatePassListener.
 *
 * The Symfony Lock store only lets the owner release a lock (token check), so a lock
 * left behind by a killed worker has to be removed straight from the Redis store.
 */
final class PopulateLockManager
{
    public const string LOCK_PREFIX = 'es_populate_';

    private \Redis|\Predis\ClientInterface|null $redis = null;

    public function __construct(
        private readonly string $redisDsn,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public static function getLockName(string $indexName): string
    {
        return self::LOCK_PREFIX.$indexName;
    }

    public function isLocked(string $indexName): bool
    {
        return (bool) $this->redis()->exists(self::getLockName($indexName));
    }

    /**
     * @param list<string> $indices logical index names to check
     *
     * @return list<string>
     */
    public function getLockedIndices(array $indices): array
    {
        return array_values(array_filter($indices, $this->isLocked(...)));
    }

    /**
     * Removes the lock whatever its owner, and closes the passes still open for
     * this index (their process is considered dead).
     *
     * @return array{lockReleased: bool, passesClosed: int}
     */
    public function forceRelease(string $indexName): array
    {
        $lockReleased = $this->deleteLock($indexName);

        $passes = $this->em->getRepository(PopulatePass::class)->findBy([
            'endedAt' => null,
            'indexName' => $indexName,
        ]);
        foreach ($passes as $pass) {
            $pass->setError('Interrupted: populate lock released manually');
            $pass->setEndedAt(new \DateTimeImmutable());
            $this->em->persist($pass);
        }
        if ([] !== $passes) {
            $this->em->flush();
        }

        return ['lockReleased' => $lockReleased, 'passesClosed' => \count($passes)];
    }

    /**
     * Closes the passes left open by a populate process that died (OOM, killed…) and
     * frees the lock of their index.
     *
     * Only the passes created since $since are touched: the lock guarantees a single
     * open pass per index, and a pass refused because the index was locked by another
     * run is closed right away, so the lock of another run is never released.
     *
     * @return int the number of closed passes
     */
    public function closeDeadPasses(?string $indexName, \DateTimeImmutable $since, string $error): int
    {
        $queryBuilder = $this->em->getRepository(PopulatePass::class)->createQueryBuilder('p')
            ->andWhere('p.endedAt IS NULL')
            ->andWhere('p.createdAt >= :since')
            // createdAt is stored without its microseconds
            ->setParameter('since', $since->setTime((int) $since->format('H'), (int) $since->format('i'), (int) $since->format('s')), Types::DATETIME_IMMUTABLE);
        if (null !== $indexName) {
            $queryBuilder
                ->andWhere('p.indexName = :indexName')
                ->setParameter('indexName', $indexName);
        }

        /** @var list<PopulatePass> $passes */
        $passes = $queryBuilder->getQuery()->getResult();
        foreach ($passes as $pass) {
            $pass->setError(substr($error, 0, 255));
            $pass->setEndedAt(new \DateTimeImmutable());
            $this->em->persist($pass);
            $this->deleteLock($pass->getIndexName());
        }
        if ([] !== $passes) {
            $this->em->flush();
        }

        return \count($passes);
    }

    private function deleteLock(string $indexName): bool
    {
        return ((int) $this->redis()->del(self::getLockName($indexName))) > 0;
    }

    private function redis(): \Redis|\Predis\ClientInterface
    {
        if (null === $this->redis) {
            $connection = RedisAdapter::createConnection($this->redisDsn);
            if (!$connection instanceof \Redis && !$connection instanceof \Predis\ClientInterface) {
                throw new \LogicException(\sprintf('Unsupported Redis client "%s" for the populate lock store.', get_debug_type($connection)));
            }
            $this->redis = $connection;
        }

        return $this->redis;
    }
}
