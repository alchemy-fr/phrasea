<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SameWorkspaceConstraint extends Constraint
{
    public array $properties = [];

    public function __construct(array $properties, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct([], $groups, $payload);
        $this->properties = $properties;
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
