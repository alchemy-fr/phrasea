<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class ESPopulate
{
    public function __construct(
        /**
         * Logical FOS Elastica index name (e.g. "asset") or null to populate every index.
         */
        public ?string $index = null,
    ) {
    }
}
