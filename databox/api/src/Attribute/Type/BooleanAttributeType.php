<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BooleanAttributeType extends AbstractAttributeType
{
    public const NAME = 'boolean';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'boolean';
    }

    public function normalizeValue($value)
    {
        return (bool) $value;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!is_bool($value)) {
            $context->addViolation('Invalid boolean');
        }
    }
}
