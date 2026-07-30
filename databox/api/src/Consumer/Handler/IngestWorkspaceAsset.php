<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('task')]
final readonly class IngestWorkspaceAsset
{
    public function __construct(
        public string $taskId,
        public string $assetId,
    ) {
    }
}
