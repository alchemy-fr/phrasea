<?php

declare(strict_types=1);

namespace App\Service\Workflow\Event;

use Alchemy\Workflow\Event\WorkflowEvent;

final class AssetIngestWorkflowEvent
{
    final public const string EVENT = 'asset_ingest';

    /**
     * Name of the root workflow (config/workflows/asset-ingest.yaml). The run
     * of a given workspace is named "<root>:<workspaceId>" (see IntegrationWorkflowRepository).
     */
    final public const string WORKFLOW_NAME = 'asset-ingest';

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

    public static function getWorkflowName(string $workspaceId): string
    {
        return self::WORKFLOW_NAME.':'.$workspaceId;
    }
}
