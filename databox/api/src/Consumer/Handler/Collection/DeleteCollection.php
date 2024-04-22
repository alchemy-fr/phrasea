<?php

namespace App\Consumer\Handler\Collection;

final readonly class DeleteCollection
{
    public function __construct(
        private string $id
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
