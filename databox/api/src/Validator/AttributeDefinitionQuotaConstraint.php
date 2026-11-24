<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Bounds the number of attribute definitions of a workspace (ATTRIBUTE_DEFINITIONS_MAX_PER_WORKSPACE):
 * each one adds fields to the asset index mapping, shared by every workspace.
 */
#[\Attribute]
class AttributeDefinitionQuotaConstraint extends Constraint
{
    public string $message = 'This workspace already has {{ limit }} attribute definitions, the maximum allowed.';

    #[\Override]
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
