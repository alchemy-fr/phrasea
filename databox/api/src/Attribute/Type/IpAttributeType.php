<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class IpAttributeType extends AbstractAttributeType
{
    final public const string NAME = 'ip';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'ip';
    }

    public function supportsAggregation(): bool
    {
        return true;
    }

    public function supportsSuggest(): bool
    {
        return true;
    }
}
