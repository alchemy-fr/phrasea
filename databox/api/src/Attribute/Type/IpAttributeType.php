<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class IpAttributeType extends AbstractAttributeType
{
    public const NAME = 'ip';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'ip';
    }
}
