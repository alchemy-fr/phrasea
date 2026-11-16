<?php

declare(strict_types=1);

namespace App\Validation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class SecureTargetUrlValidator extends ConstraintValidator
{
    private const array INSECURE_ENVIRONMENTS = ['dev', 'test'];

    public function __construct(
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SecureTargetUrl) {
            throw new UnexpectedTypeException($constraint, SecureTargetUrl::class);
        }

        // The format and the other schemes are checked by the Url constraint of the property
        if (!is_string($value) || 'http' !== strtolower((string) parse_url($value, PHP_URL_SCHEME))) {
            return;
        }

        $host = (string) parse_url($value, PHP_URL_HOST);
        if (in_array($this->environment, self::INSECURE_ENVIRONMENTS, true)
            || ('' !== $host && !str_contains($host, '.'))) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
