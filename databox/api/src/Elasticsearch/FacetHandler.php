<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\CollectionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
use Elastica\Query;
use Symfony\Component\Security\Core\Security;

class FacetHandler
{
    public const FACET_PRIVACY = 'p';
    public const FACET_TAG = 't';
    public const FACET_WORKSPACE = 'ws';
    public const FACET_COLLECTION = 'c';
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
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
                    'label' => $this->em->find(Tag::class, $bucket['key'])->getName(),
                ];

                return $bucket;
            }, $facets[self::FACET_TAG]['buckets']);
        }

        if (isset($facets[self::FACET_COLLECTION])) {
            $facets[self::FACET_COLLECTION]['buckets'] = array_values(array_filter(array_map(function (array $bucket): ?array {
                $label = $this->normalizeCollectionPath($bucket['key']);
                if (null === $label) {
                    return null;
                }

                $bucket['key'] = [
                    'value' => $bucket['key'],
                    'label' => $label,
                ];

                return $bucket;
            }, $facets[self::FACET_COLLECTION]['buckets'])));
        }

        return $facets;
    }

    private function normalizeCollectionPath(string $path): ?string
    {
        $ids = explode('/', $path);
        array_shift($ids);

        /** @var Collection[] $collections */
        $collections = array_filter(array_map(function (string $id): ?Collection {
            return $this->em->find(Collection::class, $id);
        }, $ids));

        if (empty($collections) || count($collections) < count($ids)) {
            return null;
        }

        if (!$this->security->isGranted(CollectionVoter::READ, $collections[count($collections) - 1])) {
            return null;
        }

        return implode(' / ', array_map(function (Collection $c): ?string {
            return $c->getTitle() ?? $c->getId();
        }, $collections));
    }
}
