<?php

namespace App\Elasticsearch\AQL\Function;

final readonly class Argument
{
    public function __construct(
        private string $name,
        private TypeEnum $type,
        private ?string $description = null,
        private bool $required = true,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): TypeEnum
    {
        return $this->type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }
}
