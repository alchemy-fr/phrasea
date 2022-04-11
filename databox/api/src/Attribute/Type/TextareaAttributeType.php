<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class TextareaAttributeType extends TextAttributeType
{
    public const NAME = 'textarea';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function supportsAggregation(): bool
    {
        return false;
    }
}
