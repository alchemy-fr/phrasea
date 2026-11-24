<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('task')]
final readonly class IndexAllCollections
{
    public function __construct(public ?string $workspaceId = null)
    {
    }
}
