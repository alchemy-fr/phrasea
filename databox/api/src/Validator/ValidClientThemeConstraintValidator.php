<?php

declare(strict_types=1);

namespace App\Validator;

use App\Service\Admin\ClientThemeNormalizer;
use App\Service\Admin\InvalidClientThemeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidClientThemeConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly ClientThemeNormalizer $normalizer)
    {
    }

    /**
     * @param string|null                $value
     * @param ValidClientThemeConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === trim((string) $value)) {
            return;
        }

        try {
            $data = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->context
                ->buildViolation('Invalid JSON: {{ error }}')
                ->setParameter('{{ error }}', $e->getMessage())
                ->addViolation();

            return;
        }

        try {
            $this->normalizer->normalize($data);
        } catch (InvalidClientThemeException $e) {
            foreach ($e->getViolations() as $violation) {
                $this->context
                    ->buildViolation('{{ property }}: {{ message }}')
                    ->setParameter('{{ property }}', '' !== $violation['propertyPath'] ? $violation['propertyPath'] : 'theme')
                    ->setParameter('{{ message }}', $violation['message'])
                    ->addViolation();
            }
        }
    }
}
