<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Hierarchy;

use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Attribute\Hierarchy\HierarchyAttributeFiller;
use App\Doctrine\Listener\HierarchyAttributeListener;
use App\Entity\Core\Asset;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FillAssetsHierarchyHandler
{
    public function __construct(
        private HierarchyAttributeFiller $filler,
        private EntityManagerInterface $em,
        private SearchIndexer $searchIndexer,
        private HierarchyAttributeListener $hierarchyAttributeListener,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FillAssetsHierarchy $message): void
    {
        $assetIds = $message->getAssetIds();
        $report = $this->em->wrapInTransaction(function () use ($message, $assetIds) {
            $report = $this->filler->fill(
                $message->getWorkspaceId(),
                $assetIds,
                recompute: true,
                listId: $message->getListId(),
            );

            try {
                $this->hierarchyAttributeListener->disabled = true;
                $this->em->flush();
            } finally {
                $this->hierarchyAttributeListener->disabled = false;
            }

            return $report;
        });

        foreach ($report->getConflicts() as ['assetId' => $assetId, 'conflict' => $conflict]) {
            $this->logger->warning(sprintf('Asset "%s": %s', $assetId, $conflict->getMessage()), [
                'assetId' => $assetId,
                'definitionId' => $conflict->definition->getId(),
            ]);
        }

        if (!empty($changed = $report->getChangedAssetIds())) {
            $this->searchIndexer->scheduleObjectsIndex(Asset::class, $changed, Operation::Upsert);
        }
    }
}
