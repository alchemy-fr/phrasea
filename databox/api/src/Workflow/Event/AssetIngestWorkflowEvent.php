<?php

declare(strict_types=1);

namespace App\Workflow\Event;

use Alchemy\Workflow\Event\WorkflowEvent;

final class AssetIngestWorkflowEvent
{
    final public const string EVENT = 'asset_ingest';

    public static function createEvent(string $assetId, string $workspaceId): WorkflowEvent
    {
        return new WorkflowEvent(
            self::EVENT,
            [
                'assetId' => $assetId,
                'workspaceId' => $workspaceId,
            ]
        );
    }
}
