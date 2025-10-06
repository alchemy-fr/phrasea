<?php

declare(strict_types=1);

namespace App\Workflow\Event;

use Alchemy\Workflow\Event\WorkflowEvent;

final class IncomingUploaderFileWorkflowEvent
{
    final public const string EVENT = 'incoming_uploader_file';

    public static function createEvent(
        string $baseUrl,
        string $assetId,
        string $userId,
        string $token,
        ?string $collectionId = null,
        ?string $workspaceId = null,
        ?string $storyCollectionId = null,
    ): WorkflowEvent {
        $payload = [
            'baseUrl' => $baseUrl,
            'assetId' => $assetId,
            'userId' => $userId,
            'token' => $token,
        ];
        if (null !== $collectionId) {
            $payload['collectionId'] = $collectionId;
        }
        if (null !== $workspaceId) {
            $payload['workspaceId'] = $workspaceId;
        }
        if (null !== $storyCollectionId) {
            $payload['storyCollectionId'] = $storyCollectionId;
        }

        return new WorkflowEvent(
            self::EVENT,
            $payload
        );
    }
}
