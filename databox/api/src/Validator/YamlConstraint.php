<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

class YamlConstraint extends Constraint
{
    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
