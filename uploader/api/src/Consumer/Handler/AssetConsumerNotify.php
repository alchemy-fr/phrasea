<?php

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Notify remote consumer that there is a new batch available.
 */
#[MessengerMessage('p2')]
final readonly class AssetConsumerNotify
{
    public function __construct(
        private string $id,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
