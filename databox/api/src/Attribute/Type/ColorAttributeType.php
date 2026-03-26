<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class ColorAttributeType extends KeywordAttributeType
{
    public const string NAME = 'color';

    public function isLocaleAware(): bool
    {
        return true;
    }
}
