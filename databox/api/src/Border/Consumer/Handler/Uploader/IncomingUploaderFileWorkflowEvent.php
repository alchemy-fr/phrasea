<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler\Uploader;

use Alchemy\Workflow\Event\WorkflowEvent;

final class IncomingUploaderFileWorkflowEvent
{
    final public const EVENT = 'incoming_uploader_file';

    public static function createEvent(string $baseUrl, string $assetId, string $token): WorkflowEvent
    {
        return new WorkflowEvent(
            self::EVENT,
            [
                'baseUrl' => $baseUrl,
                'assetId' => $assetId,
                'token' => $token,
            ]
        );
    }
}
