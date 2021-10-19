<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Core\CollectionAsset;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SameWorkspaceConstraintValidator extends ConstraintValidator
{
    /**
     * @param CollectionAsset      $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        if ($value instanceof CollectionAsset) {
            if ($value->getAsset() && $value->getCollection()) {
                if ($value->getAsset()->getWorkspace() !== $value->getCollection()->getWorkspace()) {
                    $this->context
                        ->buildViolation('Asset and collection are not in the same workspace')
                        ->addViolation();
                }
            }
        }
    }
}
