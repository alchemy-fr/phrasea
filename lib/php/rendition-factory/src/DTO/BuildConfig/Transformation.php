<?php

namespace Alchemy\RenditionFactory\DTO\BuildConfig;

final readonly class Transformation
{
    public function __construct(
        private string $module,
        private bool $enabled,
        private array $options,
        private ?string $description,
    ) {
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'enabled' => $this->enabled,
            'options' => $this->options,
            'description' => $this->description,
        ];
    }
}
