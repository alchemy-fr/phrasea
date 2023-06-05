<?php

declare(strict_types=1);

namespace App\Workflow\Event;

use Alchemy\Workflow\Event\WorkflowEvent;

final class AttributeUpdateWorkflowEvent
{
    final public const EVENT = 'attribute_update';

    public static function createEvent(array $attributeDefinitionIds, string $assetId, string $workspaceId): WorkflowEvent
    {
        return new WorkflowEvent(
            self::EVENT,
            [
                'attributes' => $attributeDefinitionIds,
                'assetId' => $assetId,
                'workspaceId' => $workspaceId,
            ]
        );
    }
}
