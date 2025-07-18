<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\ESBundle\Service\IndexRemover;
use App\Elasticsearch\Mapping\IndexSyncState;
use App\Entity\Admin\PopulatePass;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Persister\Event\OnExceptionEvent;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PopulatePassListener implements EventSubscriberInterface
{
    private array $pendingPasses = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IndexSyncState $indexSyncState,
        private readonly IndexRemover $indexRemover,
    ) {
    }

    public function preIndexPopulate(PreIndexPopulateEvent $event): void
    {
        $indexName = $event->getIndex();

        $this->cleanOrphanIndex($indexName);

        $currentPopulate = $this->em->getRepository(PopulatePass::class)->findOneBy([
            'endedAt' => null,
            'indexName' => $indexName,
        ]);
        if (null !== $currentPopulate) {
            if ('cli' == php_sapi_name()) {
                $this->em->remove($currentPopulate);
            } else {
                throw new \RuntimeException(sprintf('There is a current populate command running. If this last has failed, consider removing the %s row', PopulatePass::class));
            }
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
    }

    public function postInsertObjects(PostInsertObjectsEvent $event): void
    {
        $indexName = $event->getOptions()['indexName'];
        $populatePass = $this->getPass($indexName);
        $populatePass->setProgress($populatePass->getProgress() + count($event->getObjects()));
        $this->em->persist($populatePass);
        $this->em->flush();
    }

    public function onException(OnExceptionEvent $event): void
    {
        $indexName = $event->getOptions()['indexName'];
        $populatePass = $this->getPass($indexName);
        $populatePass->setError(substr($event->getException()->getMessage(), 0, 255));
        $populatePass->setEndedAt(new \DateTimeImmutable());
        $this->em->persist($populatePass);
        $this->em->flush();

        $this->cleanOrphanIndex($indexName);
    }

    private function cleanOrphanIndex(string $indexName): void
    {
        $this->indexRemover->removeIndices(
            $indexName,
            oldsOnly: true,
        );
    }

    private function getPass(string $indexName): PopulatePass
    {
        /** @var PopulatePass $populatePass */
        $populatePass = $this->em->find(PopulatePass::class, $this->pendingPasses[$indexName]);
        if (null === $populatePass) {
            $this->cleanOrphanIndex($indexName);
            // Pass has been deleted/cancelled
            throw new \RuntimeException('Populate cancelled');
        }

        return $populatePass;
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
