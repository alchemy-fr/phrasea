<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Admin\PopulatePass;
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
        $lockReleased = ((int) $this->redis()->del(self::getLockName($indexName))) > 0;

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
