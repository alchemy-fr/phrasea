<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Yaml\Exception\ParseException;

class TwigConstraintValidator extends ConstraintValidator
{
    /**
     * @param string         $value
     * @param TwigConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (empty($value)) {
            return;
        }

        try {

        } catch (ParseException $e) {
            $this->context
                ->buildViolation(sprintf(
                    'YAML error: %s',
                    $e->getMessage()
                ))
                ->addViolation();
        }
    }
}
