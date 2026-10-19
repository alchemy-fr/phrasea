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

        if ($definition->isAllowInvalid()) {
            return;
        }

        $type = $this->typeRegistry->getStrictType($definition->getType());

        $v = $value->getValue();
        if (null === $v) {
            return;
        }
        // validate() expects the normalized input value, not the denormalized one
        // (e.g. an entity ID rather than the entity representation).
        $normalized = $type->normalizeValue($v);
        if (null === $normalized) {
            return;
        }

        foreach ($type->validate($normalized) ?? [] as $error) {
            $this->context->buildViolation($error)
                ->atPath('value')
                ->setInvalidValue($v)
                ->addViolation();
        }
    }
}
