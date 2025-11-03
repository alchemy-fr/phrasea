<?php

namespace App\Integration\Phrasea\Uploader\Message;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Attribute\AttributeDataImporter;
use App\Border\UploaderClient;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationManager;
use App\Service\Asset\AssetManager;
use App\Service\Workflow\Action\AcceptFileAction;
use App\Service\Workflow\Event\IncomingUploaderFileWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IngestUploaderCommitHandler
{
    public function __construct(
        private UploaderClient $uploaderClient,
        private IntegrationManager $integrationManager,
        private WorkflowOrchestrator $workflowOrchestrator,
        private AssetManager $assetManager,
        private EntityManagerInterface $em,
        private AttributeDataImporter $attributeDataImporter,
    ) {
    }

    public function __invoke(IngestUploaderCommit $message): void
    {
        $workspaceIntegration = $this->integrationManager->loadIntegration($message->integrationId);
        $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);

        $commit = $this->uploaderClient->getCommit(
            $config['baseUrl'],
            $message->commitId,
            $message->token,
        );

        $formData = $commit['formData'] ?? [];
        $formLocale = $commit['formLocale'] ?? null;
        $storyAsset = null;
        if ($formData[AttributeDataImporter::BUILT_IN_ATTRIBUTE_PREFIX.'is_story'] ?? false) {
            $storyAsset = $this->createStory($commit['userId'], $formData, $formLocale, $config);
        }

        $userId = $commit['userId'];
        foreach ($commit['assets'] as $assetId) {
            $this->workflowOrchestrator->dispatchEvent(IncomingUploaderFileWorkflowEvent::createEvent(
                $config['baseUrl'],
                str_replace('/assets/', '', $assetId),
                $userId,
                $message->token,
                $config['collectionId'] ?? null,
                $config->getWorkspaceId(),
                $storyAsset?->getStoryCollection()->getId() ?? null
            ));
        }
    }

    private function createStory(string $userId, array $formData, ?string $formLocale, IntegrationConfig $config): Asset
    {
        $workspace = null;
        if (null !== $config->getWorkspaceId()) {
            $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $config->getWorkspaceId());
        }

        $collection = null;
        $collectionId = $formData[AcceptFileAction::COLLECTION_DESTINATION] ?? $config['collectionId'] ?? null;
        if ($collectionId) {
            $collection = DoctrineUtil::findStrict($this->em, Collection::class, $collectionId);
            if (null !== $workspace && $collection->getWorkspaceId() !== $workspace->getId()) {
                throw new \InvalidArgumentException('Collection does not belong to the configured workspace');
            }

            $workspace ??= $collection->getWorkspace();
        }

        if (null === $workspace) {
            throw new \InvalidArgumentException('Missing workspace configuration');
        }

        $storyAsset = new Asset();
        $storyAsset->setOwnerId($userId);
        $storyAsset->setWorkspace($workspace);
        if (null !== $collection) {
            $storyAsset->setReferenceCollection($collection);
            $storyAsset->addToCollection($collection);
        }
        $this->assetManager->turnIntoStory($storyAsset);

        if (!empty($formData)) {
            $this->attributeDataImporter->importAttributes($storyAsset, $formData, $formLocale);
        }

        $this->em->persist($storyAsset);
        $this->em->flush();

        return $storyAsset;
    }
}
