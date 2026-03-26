<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class SizeAttributeType extends NumberAttributeType
{
    public static function getName(): string
    {
        return 'size';
    }
}
