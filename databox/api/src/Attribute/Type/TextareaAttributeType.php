<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class TextareaAttributeType extends TextAttributeType
{
    public static function getName(): string
    {
        return 'textarea';
    }

    public function supportsAggregation(): bool
    {
        return false;
    }
}
