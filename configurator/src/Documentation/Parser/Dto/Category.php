<?php

namespace App\Documentation\Parser\Dto;

final class Category
{
    public function __construct(
        public string $name,
        public ?string $title = null,
        public ?string $description = null,
        private array $envVars = [],
    ) {
    }

    public function getEnvVars(): array
    {
        return $this->envVars;
    }

    public function addEnvVar(EnvVar $envVar): void
    {
        $this->envVars[] = $envVar;
    }
}
