<?php

declare(strict_types=1);

namespace App\Validator;

use Alchemy\RenditionFactory\Config\BuildConfigValidator;
use Alchemy\RenditionFactory\Config\YamlLoader;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidRenditionDefinitionConstraintValidator extends ConstraintValidator
{
    /** @uses BuildConfigValidator */
    public function __construct(private readonly YamlLoader $yamlLoader, private readonly BuildConfigValidator $validator)
    {
    }

    /**
     * @param string $value
     * @param ValidRenditionDefinitionConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if(!$value) {
            return;
        }
        try {
            $config = $this->yamlLoader->parse($value);
            $this->validator->validate($config);
        } catch (\Exception $e) {
            $this->context
                ->buildViolation($e->getMessage())
                ->addViolation();
        }
    }
}
