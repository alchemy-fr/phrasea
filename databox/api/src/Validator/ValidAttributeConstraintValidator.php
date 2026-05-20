<?php

declare(strict_types=1);

namespace App\Validator;

use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AbstractBaseAttribute;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidAttributeConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly AttributeTypeRegistry $typeRegistry)
    {
    }

    /**
     * @param AbstractBaseAttribute    $value
     * @param ValidAttributeConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        $definition = $value->getDefinition();
        if (null === $definition) {
            return;
        }

        $type = $this->typeRegistry->getStrictType($definition->getType());

        $v = $value->getValue();
        if (null === $v) {
            return;
        }

        $this->context->setNode($v, $value, null, 'value');
        $type->validate($v, $this->context);
    }
}
