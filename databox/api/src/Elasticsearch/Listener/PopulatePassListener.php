<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Elasticsearch\Mapping\IndexSyncState;
use App\Entity\Admin\PopulatePass;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PopulatePassListener implements EventSubscriberInterface
{
    private array $pendingPasses = [];
    private EntityManagerInterface $em;
    private IndexSyncState $indexSyncState;

    public function __construct(
        EntityManagerInterface $em,
        IndexSyncState $indexSyncState
    )
    {
        $this->em = $em;
        $this->indexSyncState = $indexSyncState;
    }

    public function preIndexPopulate(PreIndexPopulateEvent $event)
    {
        $indexName = $event->getIndex();

        $currentPopulate = $this->em->getRepository(PopulatePass::class)->findOneBy([
            'endedAt' => null,
            'indexName' => $indexName,
        ]);
        if (null !== $currentPopulate) {
            throw new RuntimeException(sprintf('There is a current populate command running. If this last has failed, consider removing the %s row', PopulatePass::class));
        }

        $populatePass = new PopulatePass();
        $populatePass->setIndexName($indexName);

        $mapping = $this->indexSyncState->getCurrentConfigMapping($indexName);
        $entityName = $mapping['mappings']['_meta']['model'];
        $populatePass->setMapping($mapping);

        $count = $this->em->getRepository($entityName)
            ->createQueryBuilder('t')
            ->select('COUNT(t) as total')
            ->getQuery()
            ->getSingleScalarResult();
        $populatePass->setDocumentCount((int) $count);

        $this->em->persist($populatePass);
        $this->em->flush();

        $this->pendingPasses[$indexName] = $populatePass->getId();
    }

    public function postIndexPopulate(PostIndexPopulateEvent $event)
    {
        $indexName = $event->getIndex();
        /** @var PopulatePass $populatePass */
        $populatePass = $this->em->find(PopulatePass::class, $this->pendingPasses[$event->getIndex()]);

        $populatePass->setEndedAt(new DateTimeImmutable());
        $this->em->persist($populatePass);

        $this->indexSyncState->snapshotStateMapping($indexName);

        $this->em->flush();
    }

    public static function getSubscribedEvents()
    {
        return [
            PreIndexPopulateEvent::class => 'preIndexPopulate',
            PostIndexPopulateEvent::class => 'postIndexPopulate',
        ];
    }
}
