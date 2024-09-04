<?php

namespace Alchemy\RenditionFactory\DTO\BuildConfig;

final readonly class Transformation
{
    public function __construct(
        private string $module,
        private array $options,
        private ?string $description,
    )
    {
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
