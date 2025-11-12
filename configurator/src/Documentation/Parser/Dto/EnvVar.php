<?php

namespace App\Documentation\Parser\Dto;

class EnvVar
{
    public function __construct(
        public string $name,
        public ?string $defaultValue,
        public ?string $description,
        public bool $deprecated = false,
        public ?bool $allowEmpty = null,
        public bool $changeMe = false,
        public array $tags = [],
        public ?string $type = null,
        public ?string $example = null,
        public ?string $rawSecret = null,
    ) {
    }
}
