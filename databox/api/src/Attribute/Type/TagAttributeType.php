<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class TagAttributeType extends KeywordAttributeType
{
    public static function getName(): string
    {
        return 'tag';
    }
}
