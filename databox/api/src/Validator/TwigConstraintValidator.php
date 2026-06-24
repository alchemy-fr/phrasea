<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Source;

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

        $twig = new Environment(new ArrayLoader());

        try {
            $source = new Source((string) $value, 'template');

            $stream = $twig->tokenize($source);
            $twig->parse($stream);
        } catch (SyntaxError $e) {
            $this->context
                ->buildViolation(sprintf(
                    'Twig syntax error: %s',
                    $e->getMessage()
                ))
                ->addViolation();
        }
    }
}
