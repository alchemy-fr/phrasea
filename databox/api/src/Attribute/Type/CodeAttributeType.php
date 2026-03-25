<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class CodeAttributeType extends TextareaAttributeType
{
    public const string NAME = 'code';

    public function isMappingLocaleAware(): bool
    {
        return false;
    }
}
