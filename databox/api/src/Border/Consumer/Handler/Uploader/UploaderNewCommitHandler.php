<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler\Uploader;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Border\Model\Upload\IncomingUpload;
use App\Workflow\Event\IncomingUploaderFileWorkflowEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UploaderNewCommitHandler
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
    ) {
    }

    public function __invoke(UploaderNewCommit $message): void
    {
        $upload = IncomingUpload::fromArray($message->getPayload());

        foreach ($upload->assets as $assetId) {
            $this->workflowOrchestrator->dispatchEvent(IncomingUploaderFileWorkflowEvent::createEvent(
                $upload->base_url,
                $assetId,
                $upload->token,
            ));
        }
    }
}
