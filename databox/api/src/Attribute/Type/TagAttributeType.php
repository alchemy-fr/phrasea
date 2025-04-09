<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Elastica\Query;
use Elastica\Query\AbstractQuery;

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

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        return new Query\Terms($field, $value);
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
