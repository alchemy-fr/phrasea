<?php

namespace App\Documentation\Parser\Dto;

class EnvVar
{
    public function __construct(
        public string $name,
        public ?string $defaultValue,
        public ?string $description,
        public ?string $category,
        public ?bool $allowEmpty = null,
        public bool $changeMe = false,
        public array $tags = [],
        public ?string $rawSecret = null,
    ) {
    }
}
