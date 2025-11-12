<?php

declare(strict_types=1);

namespace Alchemy\ConfiguratorBundle\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidConfigurationEntryConstraint extends Constraint
{
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
