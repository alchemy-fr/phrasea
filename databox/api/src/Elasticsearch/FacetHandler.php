<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Form\PrivacyChoiceType;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
use Elastica\Query;

class FacetHandler
{
    public const FACET_PRIVACY = 'p';
    public const FACET_TAG = 't';
    public const FACET_WORKSPACE = 'ws';
    public const FACET_COLLECTION = 'c';
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildTagFacet(Query $query): void
    {
        $agg = new Aggregation\Terms(self::FACET_TAG);
        $agg->setField('tags');
        $agg->setSize(20);
        $agg->setMeta([
            'title' => 'Tags',
        ]);
        $query->addAggregation($agg);
    }

    public function buildPrivacyFacet(Query $query): void
    {
        $agg = new Aggregation\Terms(self::FACET_PRIVACY);
        $agg->setField('privacy');
        $agg->setSize(6);
        $agg->setMeta([
            'title' => 'Privacy',
        ]);
        $query->addAggregation($agg);
    }

    public function buildCollectionFacet(Query $query): void
    {
        $agg = new Aggregation\Terms(self::FACET_COLLECTION);
        $agg->setField('collectionPaths');
        $agg->setSize(10);
        $agg->setMeta([
            'title' => 'Collections',
        ]);
        $query->addAggregation($agg);
    }

    public function buildWorkspaceFacet(Query $query): void
    {
        $agg = new Aggregation\Terms(self::FACET_WORKSPACE);
        $agg->setField('workspaceId');
        $agg->setSize(10);
        $agg->setMeta([
            'title' => 'Workspace',
        ]);
        $query->addAggregation($agg);
    }

    public function normalizeBuckets(array $facets): array
    {
        if (isset($facets[self::FACET_WORKSPACE])) {
            $facets[self::FACET_WORKSPACE]['buckets'] = array_map(function (array $bucket): array {
                $bucket['key'] = [
                    'value' => $bucket['key'],
                    'label' => $this->em->find(Workspace::class, $bucket['key'])->getName(),
                ];

                return $bucket;
            }, $facets[self::FACET_WORKSPACE]['buckets']);
        }

        if (isset($facets[self::FACET_PRIVACY])) {
            $facets[self::FACET_PRIVACY]['buckets'] = array_map(function (array $bucket): array {
                $bucket['key'] = [
                    'value' => $bucket['key'],
                    'label' => WorkspaceItemPrivacyInterface::LABELS[$bucket['key']],
                ];

                return $bucket;
            }, $facets[self::FACET_PRIVACY]['buckets']);
        }

        if (isset($facets[self::FACET_TAG])) {
            $facets[self::FACET_TAG]['buckets'] = array_map(function (array $bucket): array {
                $bucket['key'] = [
                    'value' => $bucket['key'],
                    'label' =>$this->em->find(Tag::class, $bucket['key'])->getName(),
                ];

                return $bucket;
            }, $facets[self::FACET_TAG]['buckets']);
        }

        if (isset($facets[self::FACET_COLLECTION])) {
            $facets[self::FACET_COLLECTION]['buckets'] = array_map(function (array $bucket): array {
                $bucket['key'] = [
                    'value' => $bucket['key'],
                    'label' => $this->normalizeCollectionPath($bucket['key']),
                ];

                return $bucket;
            }, $facets[self::FACET_COLLECTION]['buckets']);
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
