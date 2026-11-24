<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Cleans the indexes once a list has been deleted (see EntityListDeleteProcessor): its rows are gone, as the
 * definitions bound to it, whose indexed field names are carried along.
 */
#[MessengerMessage('p1')]
final readonly class EntityListIndexCleanup
{
    /**
     * @param array<string, string> $definitions definition ID => indexed field name
     */
    public function __construct(
        public string $listId,
        public string $workspaceId,
        public array $definitions,
    ) {
    }
}
