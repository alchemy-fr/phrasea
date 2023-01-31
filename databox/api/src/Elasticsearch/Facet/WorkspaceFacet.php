<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;

final class WorkspaceFacet extends AbstractFacet
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function normalizeBucket(array $bucket): ?array
    {
        $bucket['key'] = [
            'value' => $bucket['key'],
            'label' => $this->resolveValue($this->em->find(Workspace::class, $bucket['key'])),
        ];

        return $bucket;
    }

    /**
     * @param Workspace $value
     *
     * @return string
     */
    public function resolveValue($value): string
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
