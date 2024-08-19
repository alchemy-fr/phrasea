<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class CodeAttributeType extends TextareaAttributeType
{
    public static function getName(): string
    {
        return 'code';
    }

    public function isMappingLocaleAware(): bool
    {
        return false;
    }
}
