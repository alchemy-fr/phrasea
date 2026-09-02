<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Rendition;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class BuildDynamicRendition
{
    public function __construct(
        private string $renditionId,
    ) {
    }

    public function getRenditionId(): string
    {
        return $this->renditionId;
    }
}
