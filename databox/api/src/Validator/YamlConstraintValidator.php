<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class YamlConstraintValidator extends ConstraintValidator
{
    /**
     * @param string         $value
     * @param YamlConstraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        if (empty($value)) {
            return;
        }

        try {
            Yaml::parse($value);
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
