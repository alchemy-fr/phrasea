<?php

namespace Alchemy\ConfiguratorBundle\Schema;

use Symfony\Component\Validator\Constraint;

final readonly class SchemaProperty
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $example = null,
        public array $children = [],
        /**
         * @var Constraint[]
         */
        public array $validationConstraints = [],
    ) {
        if (str_contains($this->name, '.')) {
            throw new \InvalidArgumentException('Property name cannot contain a dot.');
        }
    }
}
