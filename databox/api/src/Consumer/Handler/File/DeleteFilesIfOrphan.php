<?php

namespace App\Consumer\Handler\File;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class DeleteFilesIfOrphan
{
    public function __construct(
        private array $ids,
    ) {
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
