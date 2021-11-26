<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class BooleanAttributeType extends AbstractAttributeType
{
    public const NAME = 'boolean';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'boolean';
    }

    public function normalizeValue($value)
    {
        return (bool) $value;
    }
}
