<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AssetsRestore
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
