<?php

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class DeleteAsset
{
    public function __construct(private string $path)
    {
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
