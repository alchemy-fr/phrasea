<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;
use App\Entity\Core\Tag;

final class TagFacet extends AbstractEntityFacet
{
    /**
     * @param Tag $value
     */
    public function resolveItem($value): array
    {
        return [
            'name' => $value->getName(),
            'color' => $value->getColor(),
        ];
    }

    /**
     * @param Tag $value
     */
    protected function resolveLabel($value): string
    {
        return $value->getName();
    }

    protected function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getFieldName(): string
    {
        return 'tags';
    }

    public static function getKey(): string
    {
        return 't';
    }

    public function getValueFromAsset(Asset $asset)
    {
        return $asset->getTags();
    }

    protected function getAggregationTitle(): string
    {
        return 'Tags';
    }
}
