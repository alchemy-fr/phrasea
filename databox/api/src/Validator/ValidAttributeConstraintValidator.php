<?php

declare(strict_types=1);

namespace App\Validator;

use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\Attribute;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidAttributeConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly AttributeTypeRegistry $typeRegistry)
    {
    }

    /**
     * @param Attribute               $value
     * @param SameWorkspaceConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        $definition = $value->getDefinition();
        if (null === $definition) {
            return;
        }

        $type = $this->typeRegistry->getStrictType($definition->getFieldType());

        $this->context->setNode($value->getValue(), $value, null, 'value');
        $type->validate($value->getValue(), $this->context);
    }
}
