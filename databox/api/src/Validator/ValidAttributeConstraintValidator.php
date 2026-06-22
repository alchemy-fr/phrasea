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
        $phpValue = $type->denormalizeValue($v);
        if (null === $phpValue) {
            return;
        }

        $this->context->setNode($v, $value, null, 'value');

        $errors = $type->validate($phpValue);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->context->addViolation($error);
            }
        }
    }
}
