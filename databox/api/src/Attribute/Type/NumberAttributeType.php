<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class NumberAttributeType extends AbstractAttributeType
{
    public const NAME = 'number';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'long';
    }

    /**
     * @param int|float|string $value
     *
     * @return float
     */
    public function normalizeValue($value)
    {
        return (float) $value;
    }
}
