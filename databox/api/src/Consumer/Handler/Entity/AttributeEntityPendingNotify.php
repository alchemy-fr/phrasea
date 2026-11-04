<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Entity;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class AttributeEntityPendingNotify
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
