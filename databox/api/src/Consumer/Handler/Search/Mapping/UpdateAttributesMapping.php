<?php

namespace App\Consumer\Handler\Search\Mapping;

final readonly class UpdateAttributesMapping
{
    public function __construct(private string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
