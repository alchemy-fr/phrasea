<?php

declare(strict_types=1);

namespace App\Service\Asset;

use App\Api\Filter\Group\GroupValue;
use App\Api\Traits\UserLocaleTrait;
use App\Elasticsearch\BuiltInField\BuiltInAttributeRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\Asset;
use App\Service\Asset\Attribute\AttributesResolver;

final class AssetSortGroupMapper
{
    use UserLocaleTrait;

    public function __construct(
        private readonly FieldNameResolver $fieldNameResolver,
        private readonly BuiltInAttributeRegistry $builtInAttributeRegistry,
        private readonly AttributesResolver $attributesResolver,
    ) {
    }

    /**
     * @param Asset[] $assets
     */
    public function resolveSortGroups(iterable $assets, string $groupByKey): void
    {
        $lastGroupKey = null;

        foreach ($assets as $asset) {
            $asset->attributesIndex ??= $this->attributesResolver->resolveAssetAttributes($asset, true);
            $preferredLocales = $this->getPreferredLocales($asset->getWorkspace());
            $indexValue = null;
            foreach ($asset->attributesIndex->getDefinitions() as $definitionIndex) {
                if ($groupByKey === $this->fieldNameResolver->getFieldNameFromDefinition($definitionIndex->getDefinition())) {
                    foreach ($preferredLocales as $l) {
                        if ($definitionIndex->getDefinition()->isMultiple()) {
                            continue;
                        }
                        if (null !== $attr = $definitionIndex->getAttribute($l)) {
                            $indexValue = $attr->getValue();
                            break 2;
                        }
                    }

                    break;
                }
            }

            $groupValue = $this->getGroupValue($groupByKey, $asset, $indexValue);
            $groupKey = $groupValue->getKey();

            if ($lastGroupKey !== $groupKey) {
                $asset->groupValue = $groupValue;
                $lastGroupKey = $groupKey;
            }
        }
    }

    private function getGroupValue($groupBy, Asset $object, $indexValue): GroupValue
    {
        $builtInField = $this->builtInAttributeRegistry->getBuiltInField($groupBy);

        if (null !== $builtInField) {
            $value = $builtInField->getValueFromAsset($object);

            return $builtInField->resolveGroupValue($groupBy, $builtInField->denormalizeValue($value));
        }

        $type = $this->fieldNameResolver->getFieldFromName($groupBy)->type;
        $key = $value = $indexValue ?? null;
        if (is_array($key)) {
            $key = implode(',', $key);
        }
        $value = $type->getGroupValueLabel($type->denormalizeValue($value));

        return new GroupValue($groupBy, $type::getName(), $key, null !== $value ? [$value] : []);

    }
}
