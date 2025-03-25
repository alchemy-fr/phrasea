<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Api\Filter\Group\GroupValue;
use App\Entity\Core\Asset;
use Elastica\Query;

interface FacetInterface
{
    public function normalizeBucket(array $bucket): ?array;

    public function resolveGroupValue(string $name, $value): GroupValue;

    public function getFieldName(): string;

    public static function getKey(): string;

    public function isSortable(): bool;

    public function getValueFromAsset(Asset $asset): mixed;

    public function buildFacet(Query $query): void;

    public function getType(): string;

    public function includesMissing(): bool;

    public function normalizeValueForSearch(mixed $value): mixed;
}
