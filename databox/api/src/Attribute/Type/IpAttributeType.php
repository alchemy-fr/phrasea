<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Elastica\Query\AbstractQuery;
use Elastica\Query;

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

    public function supportsAggregation(): bool
    {
        return true;
    }

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        return new Query\Terms($field, $value);
    }
}
