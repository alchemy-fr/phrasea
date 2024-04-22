<?php

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AssetAcknowledge
{
    public function __construct(private string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

}
