<?php

namespace App\Consumer\Handler\File;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class FileDelete
{
    public function __construct(
        private array $paths,
    ) {
    }

    public function getPaths(): array
    {
        return $this->paths;
    }
}
