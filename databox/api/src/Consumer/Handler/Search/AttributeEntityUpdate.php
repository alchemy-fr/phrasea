<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AttributeEntityUpdate
{
    public function __construct(
        private string $id,
        private ?string $old = null,
        private ?string $new = null,
    )
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOld(): ?string
    {
        return $this->old;
    }

    public function getNew(): ?string
    {
        return $this->new;
    }
}
