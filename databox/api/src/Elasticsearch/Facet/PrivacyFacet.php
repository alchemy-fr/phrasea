<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
use Elastica\Query;

final class PrivacyFacet extends AbstractFacet
{
    public function normalizeBucket(array $bucket): ?array
    {
        $bucket['key'] = [
            'value' => $bucket['key'],
            'label' => $this->resolveValue($bucket['key']),
        ];

        return $bucket;
    }

    /**
     * @param int $value
     *
     * @return string
     */
    public function resolveValue($value): string
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
        return 6;
    }
}
