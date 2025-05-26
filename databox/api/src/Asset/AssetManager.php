<?php

declare(strict_types=1);

namespace App\Asset;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Attribute\AttributeDataExporter;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Workflow\WorkflowState;
use App\Workflow\Event\AssetIngestWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityRepository;

readonly class AssetManager
{
    public function __construct(
        private AttributeDataExporter $attributeDataExporter,
        private PickSourceRenditionManager $originalRenditionManager,
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
        $asset->setPendingUploadToken(null);

        if (!empty($formData)) {
            $this->attributeDataExporter->importAttributes($asset, $formData, $locale);
        }

        $this->originalRenditionManager->assignFileToOriginalRendition($asset, $file);

        $this->em->persist($asset);

        $this->postFlushStack->addCallback(function () use ($asset) {
            $this->workflowOrchestrator->dispatchEvent(
                AssetIngestWorkflowEvent::createEvent($asset->getId(), $asset->getWorkspaceId()),
                [
                    WorkflowState::INITIATOR_ID => $asset->getOwnerId(),
                ]
            );
        });
    }
}
