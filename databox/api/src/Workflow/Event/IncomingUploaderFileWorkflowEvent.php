<?php

declare(strict_types=1);

namespace App\Workflow\Event;

use Alchemy\Workflow\Event\WorkflowEvent;

final class IncomingUploaderFileWorkflowEvent
{
    final public const EVENT = 'incoming_uploader_file';

    public static function createEvent(
        string $baseUrl,
        string $assetId,
        string $userId,
        string $token,
    ): WorkflowEvent {
        return new WorkflowEvent(
            self::EVENT,
            [
                'baseUrl' => $baseUrl,
                'assetId' => $assetId,
                'userId' => $userId,
                'token' => $token,
            ]
        );
    }
}
