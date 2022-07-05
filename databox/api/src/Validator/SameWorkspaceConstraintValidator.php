<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Workspace;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SameWorkspaceConstraintValidator extends ConstraintValidator
{
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * @param CollectionAsset         $value
     * @param SameWorkspaceConstraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        $workspaceId = null;
        foreach ($constraint->properties as $propertyPath) {
            /* @var Workspace $workspace */
            try {
                $workspace = $this->getPropertyAccessor()->getValue($value, $propertyPath);
            } catch (NoSuchPropertyException|UnexpectedTypeException $e) {
                $workspace = null;
            }
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
            }
        }
    }

    private function getPropertyAccessor(): PropertyAccessorInterface
    {
        if (null === $this->propertyAccessor) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor;
    }
}
