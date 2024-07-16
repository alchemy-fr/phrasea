<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class EntityAttributeType extends TextAttributeType
{
    public const NAME = 'entity';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!Uuid::isValid($value)) {
            $context->addViolation('Invalid entity ID');
        }
    }
}
