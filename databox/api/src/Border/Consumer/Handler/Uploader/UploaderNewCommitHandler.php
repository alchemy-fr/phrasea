<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler\Uploader;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Border\Model\Upload\IncomingUpload;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class UploaderNewCommitHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'uploader_new_commit';

    public function __construct(private readonly WorkflowOrchestrator $workflowOrchestrator)
    {
    }

    public function handle(EventMessage $message): void
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

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
