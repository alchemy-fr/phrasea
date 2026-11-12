<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\ESBundle\Service\IndexRemover;
use App\Elasticsearch\Exception\PopulateAlreadyRunningException;
use App\Elasticsearch\Exception\PopulateMemoryExceededException;
use App\Elasticsearch\Mapping\IndexSyncState;
use App\Elasticsearch\PopulateLockManager;
use App\Elasticsearch\PopulatePageTracker;
use App\Entity\Admin\PopulatePass;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Persister\Event\OnExceptionEvent;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PreFetchObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PreInsertObjectsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Records one PopulatePass per populated index and guarantees that a given
 * index is never populated by two processes at the same time.
 *
 * Two concurrent populates of the same index used to delete each other's pass
 * and half-built physical index ("Populate cancelled"). A lock per index is
 * now taken before anything is touched and refreshed after every inserted page.
 *
 * The memory of every page is recorded on the pass (see PopulatePageTracker): the stats
 * of the page being indexed are written before its insertion, so that a process killed
 * by an out-of-memory error leaves its page on the pass.
 */
class PopulatePassListener implements EventSubscriberInterface
{
    /**
     * Refreshed after every inserted page, so it only has to outlive the
     * fetch + bulk insert of a single page (and a crashed worker frees the
     * index after this delay).
     */
    private const int LOCK_TTL = 1800;

    /** @var array<string, string> index name => PopulatePass id */
    private array $pendingPasses = [];

    /** @var array<string, LockInterface> index name => lock */
    private array $locks = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IndexSyncState $indexSyncState,
        private readonly IndexRemover $indexRemover,
        private readonly LockFactory $lockFactory,
        private readonly PopulatePageTracker $pageTracker,
        /**
         * Share of the memory limit that the memory left after a page must not exceed.
         */
        private readonly float $memoryGuardRatio = 0.8,
    ) {
    }

    /**
     * @see PopulateLockManager to inspect / force-release these locks
     */
    public static function getLockName(string $indexName): string
    {
        return PopulateLockManager::getLockName($indexName);
    }

    public function preIndexPopulate(PreIndexPopulateEvent $event): void
    {
        $indexName = $event->getIndex();

        $lock = $this->lockFactory->createLock(self::getLockName($indexName), self::LOCK_TTL);
        if (!$lock->acquire()) {
            $this->recordRefusedPass($indexName);

            throw new PopulateAlreadyRunningException($indexName);
        }
        $this->locks[$indexName] = $lock;

        try {
            $this->cleanOrphanIndex($indexName);

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

            $mapping = $this->indexSyncState->getCurrentConfigMapping($indexName);
            $entityName = $mapping['mappings']['_meta']['model'];
            $populatePass->setMapping($mapping);

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
        } catch (\Throwable $e) {
            $this->release($indexName);

            throw $e;
        }
    }

    public function postIndexPopulate(PostIndexPopulateEvent $event): void
    {
        $indexName = $event->getIndex();
        $populatePass = $this->getPass($indexName);
        $populatePass->setProgress($populatePass->getDocumentCount());
        $populatePass->setEndedAt(new \DateTimeImmutable());
        $populatePass->setStats($this->pageTracker->getStats());
        $this->em->persist($populatePass);

        $this->indexSyncState->snapshotStateMapping($indexName);

        $this->em->flush();

        $this->pageTracker->finish($indexName);
        $this->release($indexName);
    }

    /**
     * The hydration of the page counts in its peak.
     */
    public function beforePageFetched(PreFetchObjectsEvent $event): void
    {
        if (isset($this->pendingPasses[$event->getOptions()['indexName']])) {
            $this->pageTracker->resetPeak();
        }
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

        if (isset($this->locks[$indexName])) {
            $this->locks[$indexName]->refresh();
        }
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

        $populatePass->setError(substr($event->getException()->getMessage(), 0, 255));
        $populatePass->setEndedAt(new \DateTimeImmutable());
        $populatePass->setStats($this->pageTracker->getStats());
        $this->em->persist($populatePass);
        $this->em->flush();

        $this->cleanOrphanIndex($indexName);
        $this->release($indexName);
    }

    private function recordRefusedPass(string $indexName): void
    {
        $populatePass = new PopulatePass();
        $populatePass->setIndexName($indexName);
        $populatePass->setMapping([]);
        $populatePass->setDocumentCount(0);
        $populatePass->setProgress(0);
        $populatePass->setError((new PopulateAlreadyRunningException($indexName))->getMessage());
        $populatePass->setEndedAt(new \DateTimeImmutable());
        $this->em->persist($populatePass);
        $this->em->flush();
    }

    private function cleanOrphanIndex(string $indexName): void
    {
        $this->indexRemover->removeIndices(
            $indexName,
            oldsOnly: true,
        );
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
        $this->cleanOrphanIndex($indexName);
        $this->release($indexName);

        throw new \RuntimeException(sprintf('Populate of index "%s" cancelled: its populate pass has been deleted', $indexName));
    }

    private function release(string $indexName): void
    {
        unset($this->pendingPasses[$indexName]);
        if (isset($this->locks[$indexName])) {
            $this->locks[$indexName]->release();
            unset($this->locks[$indexName]);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreIndexPopulateEvent::class => 'preIndexPopulate',
            PostIndexPopulateEvent::class => 'postIndexPopulate',
            PreFetchObjectsEvent::class => 'beforePageFetched',
            PreInsertObjectsEvent::class => ['beforePageInserted', -1024],
            PostInsertObjectsEvent::class => [
                ['postInsertObjects', 0],
                // After PopulateListener::afterPageCleared (-1024)
                ['afterPageReleased', -1536],
            ],
            OnExceptionEvent::class => 'onException',
        ];
    }
}
