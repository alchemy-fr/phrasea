<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Attribute\AttributeDataImporter;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Workflow\WorkflowState;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;

readonly class AssetManager
{
    public function __construct(
        private AttributeDataImporter $attributeDataImporter,
        private PickSourceRenditionManager $pickSourceRenditionManager,
        private EntityManagerInterface $em,
        private WorkflowOrchestrator $workflowOrchestrator,
        private PostFlushStack $postFlushStack,
    ) {
    }

    public function assignNewAssetSourceFile(
        Asset $asset,
        File $file,
        ?array $formData = [],
        ?string $locale = null,
    ): void {
        if ($asset->getWorkspaceId() !== $file->getWorkspaceId()) {
            throw new \InvalidArgumentException('Asset and File are not in the same workspace');
        }

        $asset->setSource($file);

        if (!empty($formData)) {
            $this->attributeDataImporter->importAttributes($asset, $formData, $locale);
        }

        $this->pickSourceRenditionManager->assignFileToRenditionsPickingSource($asset, $file);

        $this->postFlushStack->addCallback(function () use ($asset) {
            $this->workflowOrchestrator->dispatchEvent(
                AssetIngestWorkflowEvent::createEvent($asset->getId(), $asset->getWorkspaceId()),
                [
                    WorkflowState::INITIATOR_ID => $asset->getOwnerId(),
                ]
            );
        });

        $this->em->persist($asset);
        $this->em->persist($file);
    }

    public function turnIntoStory(Asset $asset): void
    {
        $storyCollection = new Collection();
        $storyCollection->setWorkspace($asset->getWorkspace());
        $storyCollection->setOwnerId($asset->getOwnerId());
        $this->em->persist($storyCollection);
        $asset->setStoryCollection($storyCollection);
    }
}
