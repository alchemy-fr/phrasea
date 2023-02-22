<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;

final class PrivacyFacet extends AbstractLabelledFacet
{
    /**
     * @param int $value
     */
    public function resolveLabel($value): string
    {
        return WorkspaceItemPrivacyInterface::LABELS[$value];
    }

    public function getFieldName(): string
    {
        return 'privacy';
    }

    public static function getKey(): string
    {
        return 'p';
    }

    public function getValueFromAsset(Asset $asset)
    {
        return $asset->getPrivacy();
    }

    protected function getAggregationTitle(): string
    {
        return 'Privacy';
    }

    protected function getAggregationSize(): int
    {
        return count(WorkspaceItemPrivacyInterface::LABELS);
    }
}
