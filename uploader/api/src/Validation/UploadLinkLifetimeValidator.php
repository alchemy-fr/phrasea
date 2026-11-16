<?php

declare(strict_types=1);

namespace App\Validation;

use App\Entity\UploadLink;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UploadLinkLifetimeValidator extends ConstraintValidator
{
    public function __construct(
        #[Autowire(param: 'app.upload_link.max_ttl')]
        private readonly int $maxTtlDays,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UploadLinkLifetime) {
            throw new UnexpectedTypeException($constraint, UploadLinkLifetime::class);
        }

        // A missing expiration is reported by the NotNull constraint of the property
        if (!$value instanceof UploadLink || !$value->isEnabled() || null === $value->getExpiresAt()) {
            return;
        }

        $max = new \DateTimeImmutable()->modify(sprintf('+%d days', $this->maxTtlDays));
        if ($value->getExpiresAt() > $max) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ days }}', (string) $this->maxTtlDays)
                ->atPath('expiresAt')
                ->addViolation();
        }
    }
}
