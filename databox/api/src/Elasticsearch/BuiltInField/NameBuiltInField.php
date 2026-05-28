<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;

final class NameBuiltInField extends AbstractBuiltInAttribute
{
    public function __construct(
        private readonly AssetNameResolver $assetNameResolver,
        private readonly AttributesResolver $attributesResolver,
    ) {
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'name';
    }

    public static function getName(): string
    {
        return 'name';
    }

    public static function getKey(): string
    {
        return '@name';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        $attributesIndex = $asset->attributesIndex ?? $this->attributesResolver->resolveAssetAttributes($asset, true);

        return $this->assetNameResolver->resolveName($asset, $attributesIndex, []);
    }

    public function getType(): string
    {
        return TextAttributeType::getName();
    }

    public function isFacet(): bool
    {
        return false;
    }

    public function isSortable(): bool
    {
        return true;
    }
}
