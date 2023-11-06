<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationManager;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidIntegrationOptionsConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly IntegrationManager $integrationManager)
    {
    }

    /**
     * @param WorkspaceIntegration              $value
     * @param ValidIntegrationOptionsConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value->getIntegration()) {
            return;
        }

        try {
            $this->integrationManager->validateIntegration($value);
        } catch (InvalidConfigurationException $e) {
            $this->context
                ->buildViolation($e->getMessage())
                ->atPath('optionsJson')
                ->addViolation();
        }
    }
}
