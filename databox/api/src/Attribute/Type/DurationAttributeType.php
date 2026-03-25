<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

class DurationAttributeType extends NumberAttributeType
{
    public static function getName(): string
    {
        return 'duration';
    }

    /**
     * @param int|string $value
     *
     * @return int
     */
    public function normalizeElasticsearchValue($value)
    {
        return (int) $value;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!is_int($value)) {
            $context->addViolation('Invalid duration (milliseconds)');
        }
    }
}
