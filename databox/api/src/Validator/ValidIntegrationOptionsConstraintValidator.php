<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidIntegrationOptionsConstraintValidator extends ConstraintValidator
{
    private IntegrationManager $integrationManager;

    public function __construct(IntegrationManager $integrationManager)
    {
        $this->integrationManager = $integrationManager;
    }

    /**
     * @param WorkspaceIntegration              $value
     * @param ValidIntegrationOptionsConstraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value->getIntegration()) {
            return;
        }

        try {
            $this->integrationManager->getIntegrationOptions($value);
        } catch (\InvalidArgumentException $e) {
            $this->context
                ->buildViolation($e->getMessage())
                ->atPath('optionsJson')
                ->addViolation();
        }
    }
}
