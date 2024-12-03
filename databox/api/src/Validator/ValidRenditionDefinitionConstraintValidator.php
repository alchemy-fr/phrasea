<?php

declare(strict_types=1);

namespace App\Validator;


use Alchemy\RenditionFactory\Config\Validator;
use Alchemy\RenditionFactory\Config\YamlLoader;
use App\Entity\Core\RenditionDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidRenditionDefinitionConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly YamlLoader $yamlLoader, private readonly Validator $validator)
    {
    }

    /**
     * @param RenditionDefinition $value
     * @param ValidRenditionDefinitionConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if(!($definition = $value->getDefinition())) {
            return;
        }
        try {
            $config = $this->yamlLoader->parse($definition);
            $this->validator->validate($config);
        } catch (\Throwable $e) {
            $this->context
                ->buildViolation($e->getMessage())
                ->addViolation();
        }
    }
}
