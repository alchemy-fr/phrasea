<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Elasticsearch\Exception\PopulateOwnerAliveException;
use App\Entity\Admin\PopulatePass;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Inspects and force-releases the per-index populate locks taken by PopulatePassListener.
 *
 * The Symfony Lock store only lets the owner release a lock (token check), so a lock
 * left behind by a killed worker has to be removed straight from the Redis store.
 *
 * Next to its lock, a running populate keeps an owner record (run, pass, host, pid) whose
 * heartbeat is renewed while it indexes: a lock whose owner gave a recent sign of life is
 * not released without an explicit "even if alive".
 */
final class PopulateLockManager
{
    public const string LOCK_PREFIX = 'es_populate_';
    private const string OWNER_PREFIX = 'es_populate_owner_';
    private const string BUILDING_PREFIX = 'es_populate_building_';

    /**
     * Environment variable through which ESPopulateHandler gives its run id to the populate process.
     */
    public const string RUN_ID_ENV = 'ES_POPULATE_RUN_ID';

    /**
     * A populate whose last heartbeat is older than this is considered dead. The heartbeat is
     * renewed at every step of a page and every 30 s while its documents are transformed.
     */
    public const int HEARTBEAT_TIMEOUT = 300;

    private ?PopulateRedis $redis = null;

    private ?string $runId = null;

    public function __construct(
        private readonly string $redisDsn,
        private readonly EntityManagerInterface $em,
        /**
         * The ES index prefix of this deployment: two stacks sharing a Redis server
         * must not lock (nor release) each other's populates.
         */
        #[Autowire('%es_index_prefix%')]
        private readonly string $indexPrefix = '',
    ) {
    }

    /**
     * @param string $indexName logical index name
     */
    public function getLockName(string $indexName): string
    {
        return self::LOCK_PREFIX.$this->indexPrefix.$indexName;
    }

    /**
     * Key of a Redis entry attached to the populate of a logical index (owner, write journal…).
     */
    public function getKey(string $prefix, string $indexName): string
    {
        return $prefix.$this->indexPrefix.$indexName;
    }

    public function isLocked(string $indexName): bool
    {
        return $this->redis()->exists($this->getLockName($indexName));
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
     * Identifies the populates started by one process (or one ESPopulate message).
     */
    public function getRunId(): string
    {
        if (null === $this->runId) {
            $runId = getenv(self::RUN_ID_ENV);
            $this->runId = \is_string($runId) && '' !== $runId ? $runId : Uuid::uuid4()->toString();
        }

        return $this->runId;
    }

    /**
     * Records the owner of the lock of an index and renews its heartbeat.
     *
     * @param array{runId: string, passId: ?string, host: string, pid: int, index: ?string} $owner
     */
    public function writeOwner(string $indexName, array $owner, int $ttl): void
    {
        $owner['heartbeatAt'] = time();
        $this->redis()->setEx($this->getKey(self::OWNER_PREFIX, $indexName), $ttl, json_encode($owner, JSON_THROW_ON_ERROR));

        if (null !== $owner['index']) {
            // the physical index being built: see isBuilding()
            $this->redis()->setEx(self::BUILDING_PREFIX.$owner['index'], $ttl, $owner['runId']);
        }
    }

    /**
     * @return array{runId: string, passId: ?string, host: string, pid: int, index: ?string, heartbeatAt: int}|null
     */
    public function getOwner(string $indexName): ?array
    {
        $value = $this->redis()->get($this->getKey(self::OWNER_PREFIX, $indexName));
        if (null === $value) {
            return null;
        }

        try {
            $owner = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($owner) && isset($owner['runId'], $owner['heartbeatAt']) ? $owner : null;
    }

    /**
     * Whether the populate holding the lock of the index gave a sign of life recently.
     */
    public function isAlive(string $indexName): bool
    {
        $owner = $this->getOwner($indexName);

        return null !== $owner && time() - (int) $owner['heartbeatAt'] < self::HEARTBEAT_TIMEOUT;
    }

    /**
     * Whether a physical index is being built by a populate that is still alive: it must
     * not be removed as an orphan.
     */
    public function isBuilding(string $physicalIndexName): bool
    {
        return $this->redis()->exists(self::BUILDING_PREFIX.$physicalIndexName);
    }

    /**
     * Forgets the owner of a populate, unless another run took the index over meanwhile.
     */
    public function releaseOwner(string $indexName, string $runId, ?string $physicalIndexName): void
    {
        $keys = [];
        $owner = $this->getOwner($indexName);
        if (null !== $owner && $owner['runId'] === $runId) {
            $keys[] = $this->getKey(self::OWNER_PREFIX, $indexName);
        }
        if (null !== $physicalIndexName && $this->redis()->get(self::BUILDING_PREFIX.$physicalIndexName) === $runId) {
            $keys[] = self::BUILDING_PREFIX.$physicalIndexName;
        }
        $this->redis()->del($keys);
    }

    /**
     * Removes the lock whatever its owner, and closes the passes still open for
     * this index (their process is considered dead).
     *
     * @param bool $evenIfAlive release the lock of a populate that gave a sign of life recently
     *
     * @return array{lockReleased: bool, passesClosed: int}
     *
     * @throws PopulateOwnerAliveException
     */
    public function forceRelease(string $indexName, bool $evenIfAlive = false): array
    {
        if (!$evenIfAlive && $this->isAlive($indexName)) {
            throw new PopulateOwnerAliveException($indexName, $this->getOwner($indexName) ?? []);
        }

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
     * Only the passes of the dead run are touched, and a lock is only removed if that run
     * owns it: a populate of another run (e.g. one index populated from the admin meanwhile)
     * keeps its pass and its lock.
     *
     * @return int the number of closed passes
     */
    public function closeDeadPasses(string $runId, ?string $indexName, string $error): int
    {
        $criteria = [
            'endedAt' => null,
            'runId' => $runId,
        ];
        if (null !== $indexName) {
            $criteria['indexName'] = $indexName;
        }

        /** @var list<PopulatePass> $passes */
        $passes = $this->em->getRepository(PopulatePass::class)->findBy($criteria);
        foreach ($passes as $pass) {
            $pass->setError(substr($error, 0, 255));
            $pass->setEndedAt(new \DateTimeImmutable());
            $this->em->persist($pass);

            $owner = $this->getOwner($pass->getIndexName());
            if (null === $owner || $owner['runId'] === $runId) {
                $this->deleteLock($pass->getIndexName());
            }
        }
        if ([] !== $passes) {
            $this->em->flush();
        }

        return \count($passes);
    }

    /**
     * E.g. "pass 0f4c…, host worker-1, pid 42, last heartbeat 12 s ago".
     *
     * @param array<string, mixed> $owner
     */
    public static function describeOwner(array $owner): string
    {
        $parts = [];
        if (!empty($owner['passId'])) {
            $parts[] = 'pass '.$owner['passId'];
        }
        if (!empty($owner['host'])) {
            $parts[] = 'host '.$owner['host'];
        }
        if (!empty($owner['pid'])) {
            $parts[] = 'pid '.$owner['pid'];
        }
        if (isset($owner['heartbeatAt'])) {
            $parts[] = \sprintf('last heartbeat %d s ago', max(0, time() - (int) $owner['heartbeatAt']));
        }

        return [] !== $parts ? implode(', ', $parts) : 'unknown owner';
    }

    /**
     * @internal shared with PopulateWriteJournal
     */
    public function redis(): PopulateRedis
    {
        return $this->redis ??= new PopulateRedis($this->redisDsn);
    }

    private function deleteLock(string $indexName): bool
    {
        $this->redis()->del([$this->getKey(self::OWNER_PREFIX, $indexName)]);

        return $this->redis()->del([$this->getLockName($indexName)]) > 0;
    }
}
