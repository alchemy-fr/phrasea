<?php

declare(strict_types=1);

namespace Alchemy\ConfiguratorBundle\Validator;

use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntry;
use Alchemy\ConfiguratorBundle\Schema\SchemaProperty;
use Alchemy\ConfiguratorBundle\Service\ConfigurationReference;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidConfigurationEntryConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly ConfigurationReference $configurationReference)
    {
    }

    /**
     * @param ConfiguratorEntry                 $value
     * @param ValidConfigurationEntryConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        $key = $value->getName();

        $prop = $this->findProperty($key);
        if (null === $prop) {
            $this->context
                ->buildViolation('The configuration key "{{ key }}" is not recognized.')
                ->setParameter('{{ key }}', $key)
                ->addViolation();

            return;
        }

        $this->context
            ->getValidator()
            ->inContext($this->context)
            ->atPath('value')
            ->validate($value->getValue(), $prop->validationConstraints, $this->context->getGroup());
    }

    private function findProperty(string $path): ?SchemaProperty
    {
        $props = $this->configurationReference->getAllSchemaProperties();

        if (isset($props[$path])) {
            return $props[$path];
        }

        return null;
    }
}
