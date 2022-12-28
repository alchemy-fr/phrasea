<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class JsonAttributeType extends CodeAttributeType
{
    public static function getName(): string
    {
        return 'json';
    }
}
