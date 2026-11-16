<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Elasticsearch\Exception\PopulateAlreadyRunningException;
use App\Elasticsearch\Exception\PopulateIndexNotReadyException;
use App\Elasticsearch\Exception\PopulateLockLostException;
use App\Elasticsearch\Exception\PopulateMemoryExceededException;
use App\Elasticsearch\Mapping\IndexSyncState;
use App\Elasticsearch\PopulateIndexGuard;
use App\Elasticsearch\PopulateLockManager;
use App\Elasticsearch\PopulatePageTracker;
use App\Elasticsearch\PopulateWriteJournal;
use App\Elasticsearch\PopulateWriteReplayer;
use App\Entity\Admin\PopulatePass;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PostIndexResetEvent;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Persister\Event\OnExceptionEvent;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PreFetchObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PreInsertObjectsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Records one PopulatePass per populated index and guarantees that a given
 * index is never populated by two processes at the same time.
 *
 * Two concurrent populates of the same index used to delete each other's pass
 * and half-built physical index ("Populate cancelled"). A lock per index is
 * now taken before anything is touched; its owner (run, pass, host, pid) and
 * heartbeat are kept next to it (see PopulateLockManager) and renewed at every
 * step of a page, and while its documents are transformed. A populate that
 * loses its lock stops.
 *
 * The live writes made while the index is populated go to the old index (through
 * the alias): they are journaled (see PopulateWriteJournal) and replayed on the new
 * index right after the switch of the alias, before the lock is given back.
 *
 * The memory of every page is recorded on the pass (see PopulatePageTracker): the stats
 * of the page being indexed are written before its insertion, so that a process killed
 * by an out-of-memory error leaves its page on the pass.
 */
class PopulatePassListener implements EventSubscriberInterface
{
    /**
     * Renewed by every heartbeat: a crashed worker frees the index after this delay.
     */
    private const int LOCK_TTL = 1800;

    /**
     * Seconds between two heartbeats while the documents of a page are transformed.
     */
    private const int HEARTBEAT_INTERVAL = 30;

    /** @var array<string, string> index name => PopulatePass id */
    private array $pendingPasses = [];

    /** @var array<string, LockInterface> index name => lock */
    private array $locks = [];

    /** @var array<string, array{runId: string, passId: ?string, host: string, pid: int, index: ?string}> index name => lock owner */
    private array $owners = [];

    /** @var array<string, class-string> index name => indexed entity */
    private array $models = [];

    private float $lastHeartbeat = 0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IndexSyncState $indexSyncState,
        private readonly LockFactory $lockFactory,
        private readonly PopulatePageTracker $pageTracker,
        private readonly PopulateLockManager $lockManager,
        private readonly PopulateIndexGuard $indexGuard,
        private readonly PopulateWriteJournal $writeJournal,
        private readonly PopulateWriteReplayer $writeReplayer,
        /**
         * Share of the memory limit that the memory left after a page must not exceed.
         */
        private readonly float $memoryGuardRatio = 0.8,
    ) {
    }

    public function preIndexPopulate(PreIndexPopulateEvent $event): void
    {
        $indexName = $event->getIndex();

        // PopulateLockManager inspects / force-releases these locks
        $lock = $this->lockFactory->createLock($this->lockManager->getLockName($indexName), self::LOCK_TTL);
        if (!$lock->acquire()) {
            $this->recordRefusedPass($indexName);

            throw new PopulateAlreadyRunningException($indexName);
        }
        $this->locks[$indexName] = $lock;
        $this->owners[$indexName] = [
            'runId' => $this->lockManager->getRunId(),
            'passId' => null,
            'host' => (string) gethostname(),
            'pid' => (int) getmypid(),
            'index' => null,
        ];

        try {
            $this->heartbeat($indexName);
            // the writes recorded from now on are replayed at the end of this populate
            $this->writeJournal->reset($indexName);

            $this->indexGuard->removeOrphanIndices($indexName);

            // The lock guarantees nothing else is running on this index: an unterminated
            // pass can only be the leftover of a process that died mid-populate.
            $interrupted = $this->em->getRepository(PopulatePass::class)->findBy([
                'endedAt' => null,
                'indexName' => $indexName,
            ]);
            foreach ($interrupted as $pass) {
                $pass->setError('Interrupted: the process died before the end of the populate');
                $pass->setEndedAt(new \DateTimeImmutable());
                $this->em->persist($pass);
            }

            $populatePass = new PopulatePass();
            $populatePass->setProgress(0);
            $populatePass->setIndexName($indexName);
            $populatePass->setRunId($this->owners[$indexName]['runId']);

            $mapping = $this->indexSyncState->getCurrentConfigMapping($indexName);
            $entityName = $mapping['mappings']['_meta']['model'];
            $populatePass->setMapping($mapping);
            $this->models[$indexName] = $entityName;

            $count = $this->em
                ->getRepository($entityName)
                ->getESQueryBuilder()
                ->select('COUNT(t) as total')
                ->resetDQLPart('orderBy')
                ->getQuery()
                ->getSingleScalarResult();
            $populatePass->setDocumentCount((int) $count);

            $this->em->persist($populatePass);
            $this->em->flush();

            $this->pendingPasses[$indexName] = $populatePass->getId();
            $this->owners[$indexName]['passId'] = $populatePass->getId();
            $this->heartbeat($indexName);
        } catch (\Throwable $e) {
            $this->release($indexName);

            throw $e;
        }
    }

    /**
     * FOS Elastica has created the physical index this populate fills.
     */
    public function postIndexReset(PostIndexResetEvent $event): void
    {
        $indexName = $event->getIndex();
        if (!$event->isPopulating() || !isset($this->owners[$indexName])) {
            return;
        }

        $this->owners[$indexName]['index'] = $this->indexGuard->getBuiltIndexName($indexName);
        $this->heartbeat($indexName);
    }

    /**
     * Runs before FOS Elastica switches the alias to the new index (priority -9999).
     */
    public function postIndexPopulate(PostIndexPopulateEvent $event): void
    {
        $indexName = $event->getIndex();
        $populatePass = $this->getPass($indexName);

        $builtIndex = $this->owners[$indexName]['index'] ?? null;
        if ($event->isReset() && null !== $builtIndex) {
            try {
                $this->heartbeat($indexName);
                $this->indexGuard->assertReadyToSwitch($indexName, $builtIndex);
            } catch (PopulateIndexNotReadyException|PopulateLockLostException $e) {
                // the exception stops the dispatch: the alias is not switched
                $this->fail($indexName, $populatePass, $e);

                throw $e;
            }
        }

        $populatePass->setProgress($populatePass->getDocumentCount());
        $populatePass->setEndedAt(new \DateTimeImmutable());
        $populatePass->setStats($this->pageTracker->getStats());
        $this->em->persist($populatePass);

        $this->indexSyncState->snapshotStateMapping($indexName);

        $this->em->flush();

        $this->pageTracker->finish($indexName);

        if (!$event->isReset() || null === $builtIndex) {
            // populated in place: the live writes reached it
            $this->writeJournal->reset($indexName);
            $this->release($indexName);
        }
    }

    /**
     * Runs once FOS Elastica has switched the alias to the new index: the writes made to the
     * old one during the populate are replayed on the new one.
     */
    public function afterAliasSwitched(PostIndexPopulateEvent $event): void
    {
        $indexName = $event->getIndex();
        if (!isset($this->pendingPasses[$indexName])) {
            return;
        }

        $passId = $this->pendingPasses[$indexName];
        $builtIndex = (string) $this->owners[$indexName]['index'];
        $model = $this->models[$indexName];

        try {
            // From now on, the live writes reach the new index through the alias
            $journal = $this->writeJournal->detach($indexName, $this->owners[$indexName]['runId']);
        } finally {
            $this->release($indexName);
        }

        try {
            $replayed = $this->writeReplayer->replay($journal, $builtIndex, $model);
        } catch (\Throwable $e) {
            $this->updatePass($passId, null, substr('Writes made during the populate not replayed: '.$e->getMessage(), 0, 255));

            throw $e;
        }

        $this->updatePass($passId, $replayed);
    }

    /**
     * The hydration of the page counts in its peak.
     */
    public function beforePageFetched(PreFetchObjectsEvent $event): void
    {
        $indexName = $event->getOptions()['indexName'];
        if (!isset($this->pendingPasses[$indexName])) {
            return;
        }

        $this->pageTracker->resetPeak();
        $this->heartbeatOutsideOfInsert($indexName);
    }

    /**
     * Runs after the listeners that may filter the objects of the page.
     */
    public function beforePageInserted(PreInsertObjectsEvent $event): void
    {
        $options = $event->getOptions();
        $indexName = $options['indexName'];
        if (!isset($this->pendingPasses[$indexName])) {
            return;
        }

        $this->heartbeatOutsideOfInsert($indexName);

        // Elasticsearch would create a deleted index again on the first bulk, with a dynamic mapping
        $builtIndex = $this->owners[$indexName]['index'] ?? null;
        if (null !== $builtIndex && !$this->indexGuard->exists($builtIndex)) {
            $e = new PopulateIndexNotReadyException(\sprintf('The index "%s" was deleted while being populated', $builtIndex));
            $populatePass = $this->findPass($indexName);
            if (null !== $populatePass) {
                $this->fail($indexName, $populatePass, $e);
            } else {
                $this->release($indexName);
            }

            throw $e;
        }

        $ids = [];
        foreach ($event->getObjects() as $object) {
            if (method_exists($object, 'getId')) {
                $ids[] = (string) $object->getId();
            }
        }

        $stats = $this->pageTracker->pageStarted(
            $indexName,
            $event->getPager()->getCurrentPage(),
            (int) ($options['max_per_page'] ?? $event->getPager()->getMaxPerPage()),
            $ids,
        );

        // No row updated: the pass has been deleted, which the progress update of the page cancels
        $this->em->createQuery(sprintf('UPDATE %s p SET p.stats = :s WHERE p.id = :id', PopulatePass::class))
            ->setParameter('s', $stats, Types::JSON)
            ->setParameter('id', $this->pendingPasses[$indexName])
            ->execute();
    }

    /**
     * A page of large documents can take long to transform: the heartbeat goes on meanwhile.
     */
    public function onDocumentTransformed(PostTransformEvent $event): void
    {
        if ([] === $this->locks || hrtime(true) / 1e9 - $this->lastHeartbeat < self::HEARTBEAT_INTERVAL) {
            return;
        }

        foreach (array_keys($this->locks) as $indexName) {
            $this->heartbeat($indexName);
        }
    }

    /**
     * Runs once the page is released (see PopulateListener::afterPageCleared): its stats are
     * written with the next page, or at the end of the populate.
     */
    public function afterPageReleased(PostInsertObjectsEvent $event): void
    {
        $indexName = $event->getOptions()['indexName'];
        if (!isset($this->pendingPasses[$indexName])) {
            return;
        }

        $stats = $this->pageTracker->pageEnded();

        $memory = $stats['lastPageMemoryAfterGc'] ?? 0;
        $limit = $stats['memoryLimit'];
        if (null !== $limit && $memory > $limit * $this->memoryGuardRatio) {
            throw new PopulateMemoryExceededException($indexName, (int) $stats['page'], $memory, $limit);
        }
    }

    public function postInsertObjects(PostInsertObjectsEvent $event): void
    {
        $indexName = $event->getOptions()['indexName'];

        // A bulk update rather than a flush: the unit of work still holds the whole page here,
        // and a flush would compute its change set (and write any entity touched while indexing).
        $updated = 0;
        if (isset($this->pendingPasses[$indexName])) {
            $updated = $this->em->createQuery(sprintf('UPDATE %s p SET p.progress = COALESCE(p.progress, 0) + :n WHERE p.id = :id', PopulatePass::class))
                ->setParameter('n', count($event->getObjects()))
                ->setParameter('id', $this->pendingPasses[$indexName])
                ->execute();
        }
        if (0 === $updated) {
            $this->cancel($indexName);
        }

        // Within the try of the page: a lost lock goes through onException
        $this->heartbeat($indexName);
    }

    public function onException(OnExceptionEvent $event): void
    {
        $indexName = $event->getOptions()['indexName'];
        $populatePass = $this->findPass($indexName);
        if (null === $populatePass) {
            // Pass deleted while running (= cancelled from the admin): the original
            // exception is rethrown by the persister, do not hide it behind another one.
            $this->release($indexName);

            return;
        }

        $this->fail($indexName, $populatePass, $event->getException());
    }

    private function recordRefusedPass(string $indexName): void
    {
        $populatePass = new PopulatePass();
        $populatePass->setIndexName($indexName);
        $populatePass->setRunId($this->lockManager->getRunId());
        $populatePass->setMapping([]);
        $populatePass->setDocumentCount(0);
        $populatePass->setProgress(0);
        $populatePass->setError((new PopulateAlreadyRunningException($indexName))->getMessage());
        $populatePass->setEndedAt(new \DateTimeImmutable());
        $this->em->persist($populatePass);
        $this->em->flush();
    }

    /**
     * Renews the lock and the heartbeat of its owner.
     *
     * @throws PopulateLockLostException when the lock was released or has expired meanwhile
     */
    private function heartbeat(string $indexName): void
    {
        if (!isset($this->locks[$indexName])) {
            return;
        }

        try {
            $this->locks[$indexName]->refresh();
        } catch (LockConflictedException|LockExpiredException $e) {
            throw new PopulateLockLostException($indexName, $e);
        }

        $this->lockManager->writeOwner($indexName, $this->owners[$indexName], self::LOCK_TTL);
        $this->lastHeartbeat = hrtime(true) / 1e9;
    }

    /**
     * Outside of the insertion of a page, an exception does not reach onException.
     */
    private function heartbeatOutsideOfInsert(string $indexName): void
    {
        try {
            $this->heartbeat($indexName);
        } catch (PopulateLockLostException $e) {
            $populatePass = $this->findPass($indexName);
            if (null !== $populatePass) {
                $this->fail($indexName, $populatePass, $e);
            } else {
                $this->release($indexName);
            }

            throw $e;
        }
    }

    /**
     * Closes the pass with the error, drops the index it was building and frees the lock.
     */
    private function fail(string $indexName, PopulatePass $populatePass, \Throwable $error): void
    {
        $populatePass->setError(substr($error->getMessage(), 0, 255));
        $populatePass->setEndedAt(new \DateTimeImmutable());
        $populatePass->setStats($this->pageTracker->getStats());
        $this->em->persist($populatePass);
        $this->em->flush();

        $this->removeBuiltIndex($indexName);
        $this->release($indexName);
    }

    /**
     * @param array<string, int>|null $replayed
     */
    private function updatePass(string $passId, ?array $replayed, ?string $error = null): void
    {
        $populatePass = $this->em->find(PopulatePass::class, $passId);
        if (null === $populatePass) {
            return;
        }

        if (null !== $replayed) {
            $populatePass->setStats(array_merge($populatePass->getStats() ?? [], $replayed));
        }
        if (null !== $error) {
            $populatePass->setError($error);
        }
        $this->em->persist($populatePass);
        $this->em->flush();
    }

    /**
     * Only the index of this populate: the index being built by another populate (the lock
     * of this one was released by force…) must not be deleted.
     */
    private function removeBuiltIndex(string $indexName): void
    {
        $builtIndex = $this->owners[$indexName]['index'] ?? null;
        if (null !== $builtIndex) {
            $this->indexGuard->restoreIndexName($indexName);
            $this->indexGuard->removeBuiltIndex($builtIndex);
        }
    }

    private function findPass(string $indexName): ?PopulatePass
    {
        if (!isset($this->pendingPasses[$indexName])) {
            return null;
        }

        return $this->em->find(PopulatePass::class, $this->pendingPasses[$indexName]);
    }

    private function getPass(string $indexName): PopulatePass
    {
        return $this->findPass($indexName) ?? $this->cancel($indexName);
    }

    /**
     * The pass has been deleted: this is how a running populate gets cancelled from the admin.
     */
    private function cancel(string $indexName): never
    {
        $this->removeBuiltIndex($indexName);
        $this->release($indexName);

        throw new \RuntimeException(sprintf('Populate of index "%s" cancelled: its populate pass has been deleted', $indexName));
    }

    private function release(string $indexName): void
    {
        unset($this->pendingPasses[$indexName], $this->models[$indexName]);
        if (isset($this->locks[$indexName])) {
            $this->locks[$indexName]->release();
            unset($this->locks[$indexName]);
        }
        if (isset($this->owners[$indexName])) {
            $owner = $this->owners[$indexName];
            unset($this->owners[$indexName]);
            $this->lockManager->releaseOwner($indexName, $owner['runId'], $owner['index']);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreIndexPopulateEvent::class => 'preIndexPopulate',
            PostIndexResetEvent::class => 'postIndexReset',
            PostIndexPopulateEvent::class => [
                ['postIndexPopulate', 0],
                // After FOS\ElasticaBundle\EventListener\PopulateListener, which switches the alias (-9999)
                ['afterAliasSwitched', -10000],
            ],
            PreFetchObjectsEvent::class => 'beforePageFetched',
            PreInsertObjectsEvent::class => ['beforePageInserted', -1024],
            PostTransformEvent::class => 'onDocumentTransformed',
            PostInsertObjectsEvent::class => [
                ['postInsertObjects', 0],
                // After PopulateListener::afterPageCleared (-1024)
                ['afterPageReleased', -1536],
            ],
            OnExceptionEvent::class => 'onException',
        ];
    }
}
