<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AttributeEntityUpdate
{
    public function __construct(
        private string $id,
        private array $locales,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLocales(): array
    {
        return $this->locales;
    }
}
