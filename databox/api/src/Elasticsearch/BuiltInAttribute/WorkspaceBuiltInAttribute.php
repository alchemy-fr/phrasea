<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;

final class WorkspaceBuiltInAttribute extends AbstractEntityBuiltInAttribute
{
    use UserLocaleTrait;

    protected function getEntityClass(): string
    {
        return Workspace::class;
    }

    /**
     * @param Workspace $value
     */
    #[\Override]
    protected function resolveLabel($value): string
    {
        return $value->getTranslatedField(Workspace::TR_FIELD_NAME, $this->getPreferredLocales($value), $value->getName());
    }

    public static function getName(): string
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
