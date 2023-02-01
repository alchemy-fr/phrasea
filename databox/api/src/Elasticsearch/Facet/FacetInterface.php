<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;
use Elastica\Query;

interface FacetInterface
{
    public function normalizeBucket(array $bucket): ?array;
    public function resolveValue($value);
    public function getFieldName(): string;
    public static function getKey(): string;
    public function isValueAccessibleFromDatabase(): bool;
    public function isSortable(): bool;
    public function getValueFromAsset(Asset $asset);

    public function buildFacet(Query $query): void;

    public function getType(): string;
}
