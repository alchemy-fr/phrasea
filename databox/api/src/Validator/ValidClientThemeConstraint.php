<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * The value must be the JSON of an organisation theme (see ClientThemeNormalizer).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ValidClientThemeConstraint extends Constraint
{
}
