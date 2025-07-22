<?php

namespace App\Integration\Phrasea\Uploader\Message;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Border\UploaderClient;
use App\Integration\IntegrationManager;
use App\Workflow\Event\IncomingUploaderFileWorkflowEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IngestUploaderCommitHandler
{
    public function __construct(
        private UploaderClient $uploaderClient,
        private IntegrationManager $integrationManager,
        private WorkflowOrchestrator $workflowOrchestrator,
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

        $userId = $commit['userId'];
        foreach ($commit['assets'] as $assetId) {
            $this->workflowOrchestrator->dispatchEvent(IncomingUploaderFileWorkflowEvent::createEvent(
                $config['baseUrl'],
                str_replace('/assets/', '', $assetId),
                $userId,
                $message->token,
                $config['collectionId'] ?? null,
                $config->getWorkspaceId(),
            ));
        }
    }
}
