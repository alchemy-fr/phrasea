<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Image;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class ProcessImageSet
{
    public function __construct(
        private string $imageSetId,
    ) {
    }

    public function getImageSetId(): string
    {
        return $this->imageSetId;
    }
}
