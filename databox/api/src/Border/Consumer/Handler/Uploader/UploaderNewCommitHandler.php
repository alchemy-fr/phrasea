<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler\Uploader;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Border\Model\Upload\IncomingUpload;
use App\Border\UploaderClient;
use App\Service\Workflow\Event\IncomingUploaderFileWorkflowEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UploaderNewCommitHandler
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
        private UploaderClient $uploaderClient,
    ) {
    }

    public function __invoke(UploaderNewCommit $message): void
    {
        $upload = IncomingUpload::fromArray($message->getPayload());

        $commit = $this->uploaderClient->getCommit($upload->base_url, $upload->commit_id, $upload->token);
        $userId = $commit['userId'];

        foreach ($commit['assets'] as $assetId) {
            $this->workflowOrchestrator->dispatchEvent(IncomingUploaderFileWorkflowEvent::createEvent(
                $upload->base_url,
                str_replace('/assets/', '', $assetId),
                $userId,
                $upload->token,
            ));
        }
    }
}
