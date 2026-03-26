<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class DurationAttributeType extends NumberAttributeType
{
    public static function getName(): string
    {
        return 'duration';
    }
}
