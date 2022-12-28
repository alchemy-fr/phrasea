<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class ColorAttributeType extends KeywordAttributeType
{
    public static function getName(): string
    {
        return 'color';
    }

    public function isLocaleAware(): bool
    {
        return true;
    }
}
