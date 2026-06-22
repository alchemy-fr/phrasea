<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SameWorkspaceConstraint extends Constraint
{
    public function __construct(/**
     * @var string[]
     */
        public array $properties, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct([], $groups, $payload);
    }

    #[\Override]
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
