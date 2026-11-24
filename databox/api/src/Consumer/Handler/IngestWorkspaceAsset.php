<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;
use App\Consumer\WorkerQueue;

/**
 * Starts the ingest workflow of one asset of a workspace ingestion task
 * (queue choice: doc/tech/Databox/workers.md).
 */
#[MessengerMessage(WorkerQueue::BULK)]
final readonly class IngestWorkspaceAsset
{
    public function __construct(
        public string $taskId,
        public string $assetId,
    ) {
    }
}
