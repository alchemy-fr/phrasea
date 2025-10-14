<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;

final class WorkspaceBuiltInField extends AbstractEntityBuiltInField
{
    use UserLocaleTrait;

    protected function getEntityClass(): string
    {
        return Workspace::class;
    }

    /**
     * @param Workspace $value
     */
    protected function resolveLabel($value): string
    {
        return $value->getTranslatedField('name', $this->getPreferredLocales($value), $value->getName());
    }

    public function getFieldName(): string
    {
        return 'workspaceId';
    }

    public static function getKey(): string
    {
        return '@workspace';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getWorkspace();
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'workspace';
    }
}
