<?php

declare(strict_types=1);

namespace App\Validator;

use App\Service\Asset\Attribute\TemplateResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Twig\Error\SyntaxError;
use Twig\Sandbox\SecurityError;

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

        $twig = TemplateResolver::createEnvironment();

        try {
            // Checks the tags and functions against the sandbox policy (usually done on render)
            $twig->createTemplate((string) $value)->unwrap()->ensureSecurityChecked();
        } catch (SyntaxError $e) {
            $this->context
                ->buildViolation(sprintf(
                    'Twig syntax error: %s',
                    $e->getMessage()
                ))
                ->addViolation();
        } catch (SecurityError $e) {
            $this->context
                ->buildViolation(sprintf(
                    'Twig template not allowed: %s',
                    $e->getMessage()
                ))
                ->addViolation();
        }
    }
}
