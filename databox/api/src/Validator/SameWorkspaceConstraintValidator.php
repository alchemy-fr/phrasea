<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Core\Workspace;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SameWorkspaceConstraintValidator extends ConstraintValidator
{
    public function __construct(private PropertyAccessorInterface $propertyAccessor)
    {
    }

    /**
     * @param SameWorkspaceConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        $workspaceId = null;
        foreach ($constraint->properties as $propertyPath) {
            $workspaces = $this->getItems($value, $propertyPath);

            foreach ($workspaces as $workspace) {
                /* @var Workspace $workspace */
                $wId = $workspace ? $workspace->getId() : null;

                if (null === $wId) {
                    return;
                }

                if (null === $workspaceId) {
                    $workspaceId = $wId;
                } elseif ($workspaceId !== $wId) {
                    $this->context
                        ->buildViolation(sprintf('Items are not in the same workspace [%s]', implode(', ', $constraint->properties)))
                        ->addViolation();

                    return;
                }
            }
        }
    }

    private function getItems($value, string $propertyPath): iterable
    {
        $parts = explode('.', $propertyPath);

        $pointer = $value;
        while ($p = array_shift($parts)) {
            try {
                $pointer = $this->getPropertyAccessor()->getValue($pointer, $p);
            } catch (NoSuchPropertyException|UnexpectedTypeException) {
                return;
            }

            if (null === $pointer) {
                return;
            }

            if (is_iterable($pointer)) {
                foreach ($pointer as $item) {
                    $sub = $this->getItems($item, implode('.', $parts));
                    foreach ($sub as $s) {
                        yield $s;
                    }
                }

                return;
            }
        }

        if (null === $pointer) {
            return;
        }

        yield $pointer;
    }

    private function getPropertyAccessor(): PropertyAccessorInterface
    {
        if (null === $this->propertyAccessor) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor;
    }
}
