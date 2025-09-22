<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Api\Filter\Group\GroupValue;
use App\Entity\Core\Asset;
use Elastica\Query;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag(self::TAG)]
interface BuiltInFieldInterface
{
    final public const string TAG = 'app.built_in_field';

    public function normalizeBucket(array $bucket): ?array;

    public function resolveGroupValue(string $name, $value): GroupValue;

    public function getFieldName(): string;

    public static function getKey(): string;

    public function isSortable(): bool;

    public function getValueFromAsset(Asset $asset): mixed;

    public function buildFacet(Query $query, TranslatorInterface $translator): void;

    public function getType(): string;

    public function includesMissing(): bool;

    public function normalizeValueForSearch(mixed $value): mixed;
}
