<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/** @uses ValidRenditionDefinitionConstraintValidator */
#[\Attribute]
class ValidRenditionDefinitionConstraint extends Constraint
{
    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
