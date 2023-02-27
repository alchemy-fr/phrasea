<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;
use App\Entity\Core\RenditionDefinition;
use LogicException;

final class RenditionFacet extends AbstractEntityFacet
{
    protected function getEntityClass(): string
    {
        return RenditionDefinition::class;
    }

    /**
     * @param RenditionDefinition $value
     */
    public function resolveLabel($value): string
    {
        return $value->getName();
    }

    public function getFieldName(): string
    {
        return 'renditions';
    }

    public static function getKey(): string
    {
        return 'r';
    }

    public function getValueFromAsset(Asset $asset)
    {
        throw new LogicException('Should never be called');
    }

    protected function getAggregationTitle(): string
    {
        return 'Renditions';
    }

    public function isSortable(): bool
    {
        return false;
    }
}
