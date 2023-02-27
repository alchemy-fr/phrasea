<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;

final class WorkspaceFacet extends AbstractEntityFacet
{
    protected function getEntityClass(): string
    {
        return Workspace::class;
    }

    protected function resolveLabel($value): string
    {
        return $value->getName();
    }

    public function getFieldName(): string
    {
        return 'workspaceId';
    }

    public static function getKey(): string
    {
        return 'w';
    }

    public function getValueFromAsset(Asset $asset)
    {
        return $asset->getWorkspace();
    }

    protected function getAggregationTitle(): string
    {
        return 'Workspace';
    }
}
