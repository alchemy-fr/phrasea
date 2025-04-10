<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class TagAttributeType extends AbstractAttributeType
{
    public const string NAME = 'tag';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'text';
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
