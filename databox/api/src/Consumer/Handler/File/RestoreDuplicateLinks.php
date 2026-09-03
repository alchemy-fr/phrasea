<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class RestoreDuplicateLinks
{
    public function __construct(
        private array $fileIds,
    ) {
    }

    public function getFileIds(): array
    {
        return $this->fileIds;
    }
}
