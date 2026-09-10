<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\ESBundle\Service\IndexRemover;
use App\Elasticsearch\Exception\PopulateAlreadyRunningException;
use App\Elasticsearch\Mapping\IndexSyncState;
use App\Elasticsearch\PopulateLockManager;
use App\Entity\Admin\PopulatePass;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Persister\Event\OnExceptionEvent;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
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
        $this->em->persist($populatePass);

        $this->indexSyncState->snapshotStateMapping($indexName);

        $this->em->flush();

        $this->release($indexName);
    }

    public function postInsertObjects(PostInsertObjectsEvent $event): void
    {
        $indexName = $event->getOptions()['indexName'];
        $populatePass = $this->getPass($indexName);
        $populatePass->setProgress($populatePass->getProgress() + count($event->getObjects()));
        $this->em->persist($populatePass);
        $this->em->flush();

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
        $this->em->persist($populatePass);
        $this->em->flush();

        $this->cleanOrphanIndex($indexName);
        $this->release($indexName);
    }

    /**
     * Flags the passes started by this process and still open as failed.
     * Used when the populate command exits with an error without going through onException().
     */
    public function markPendingPassesAsFailed(string $error): void
    {
        foreach (array_keys($this->pendingPasses) as $indexName) {
            $populatePass = $this->findPass($indexName);
            if (null !== $populatePass && null === $populatePass->getEndedAt()) {
                $populatePass->setError(substr($error, 0, 255));
                $populatePass->setEndedAt(new \DateTimeImmutable());
                $this->em->persist($populatePass);
            }
            $this->release($indexName);
        }
        $this->em->flush();
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
        $populatePass = $this->findPass($indexName);
        if (null === $populatePass) {
            // Pass has been deleted: this is how a running populate gets cancelled from the admin
            $this->cleanOrphanIndex($indexName);
            $this->release($indexName);

            throw new \RuntimeException(sprintf('Populate of index "%s" cancelled: its populate pass has been deleted', $indexName));
        }

        return $populatePass;
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
            PostInsertObjectsEvent::class => 'postInsertObjects',
            OnExceptionEvent::class => 'onException',
        ];
    }
}
