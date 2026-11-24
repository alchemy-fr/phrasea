<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Import;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;
use App\Consumer\WorkerQueue;

/**
 * Creates the asset of one entry of an asset import
 * (queue choice: doc/tech/Databox/workers.md).
 */
#[MessengerMessage(WorkerQueue::BULK)]
final readonly class ImportAssetEntry
{
    /**
     * @param array       $entry        the normalized entry (see ImportEntryNormalizer)
     * @param string|null $collectionId the collection resolved from the "path" of the entry
     */
    public function __construct(
        public string $importId,
        public int $line,
        public array $entry,
        public ?string $collectionId = null,
    ) {
    }
}
