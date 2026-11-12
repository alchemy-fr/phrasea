<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Service\Asset\Attribute\AttributeEntity\EntityListClearer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EntityListIndexCleanupHandler
{
    public function __construct(
        private EntityListClearer $clearer,
    ) {
    }

    public function __invoke(EntityListIndexCleanup $message): void
    {
        $this->clearer->cleanIndexes($message->listId, $message->workspaceId, $message->definitions);
    }
}
