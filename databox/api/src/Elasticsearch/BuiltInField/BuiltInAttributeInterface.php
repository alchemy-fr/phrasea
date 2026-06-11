<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Api\Filter\Group\GroupValue;
use App\Entity\Core\Asset;
use Elastica\Query;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag(self::TAG)]
interface BuiltInAttributeInterface
{
    final public const string TAG = 'app.built_in_attribute';

    /**
     * De-normalize value from database to PHP.
     */
    public function denormalizeValue(?string $value): mixed;

    public function normalizeBuckets(array $buckets): array;

    public function resolveGroupValue(string $name, $value): GroupValue;

    public static function getName(): string;

    public static function getKey(): string;

    public function isSortable(): bool;

    public function isSearchable(): bool;

    public function isFacet(): bool;

    public function getValueFromAsset(Asset $asset): mixed;

    public function buildFacet(Query $query, TranslatorInterface $translator): void;

    public function getType(): string;

    public function isMultiple(): bool;

    public function includesMissing(): bool;

    public function normalizeValueForSearch(mixed $value): mixed;

    public function createFilterQuery(mixed $value, array $options): ?Query\AbstractQuery;

    public function isEnabled(): bool;
}
