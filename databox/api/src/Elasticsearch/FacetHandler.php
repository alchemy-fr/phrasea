<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
use Elastica\Query;

class FacetHandler
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildCollectionFacet(Query $query): void
    {
        $agg = new Aggregation\Terms('c');
        $agg->setField('collectionPaths');
        $agg->setSize(10);
        $agg->setMeta([
            'title' => 'Collections',
        ]);
        $query->addAggregation($agg);
    }

    public function buildWorkspaceFacet(Query $query): void
    {
        $agg = new Aggregation\Terms('ws');
        $agg->setField('workspaceId');
        $agg->setSize(10);
        $agg->setMeta([
            'title' => 'Workspace',
        ]);
        $query->addAggregation($agg);
    }

    public function normalizeBuckets(array $facets): array
    {
        if (isset($facets['ws'])) {
            $facets['ws']['buckets'] = array_map(function (array $bucket): array {
                $bucket['key'] = [
                    'value' => $bucket['key'],
                    'label' => $this->em->find(Workspace::class, $bucket['key'])->getName(),
                ];

                return $bucket;
            }, $facets['ws']['buckets']);
        }

        if (isset($facets['c'])) {
            $facets['c']['buckets'] = array_map(function (array $bucket): array {
                $bucket['key'] = [
                    'value' => $bucket['key'],
                    'label' => $this->normalizeCollectionPath($bucket['key']),
                ];

                return $bucket;
            }, $facets['c']['buckets']);
        }

        return $facets;
    }

    private function normalizeCollectionPath(string $path): string
    {
        $ids = explode('/', $path);
        array_shift($ids);
        $collections = $this->em->getRepository(Collection::class)->findByIds($ids);

        /** @var Collection[] $index */
        $index = [];
        foreach ($collections as $c) {
            $index[$c->getId()] = $c;
        }

        return implode(' / ', array_map(function (string $id) use ($index): ?string {
            return $index[$id]->getTitle();
        }, $ids));
    }
}
