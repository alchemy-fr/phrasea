<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AttributeEntityDelete
{
    public function __construct(
        private string $id,
    )
    {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
