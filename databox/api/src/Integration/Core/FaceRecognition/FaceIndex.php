<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use Alchemy\ESBundle\Indexer\IndexPersister;
use App\Elasticsearch\Query\Knn;
use App\Entity\Core\Attribute;
use Elastica\Index;
use Elastica\Query;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The faces of the workspaces in Elasticsearch (the "face" index): the nearest faces of an
 * embedding, instead of comparing it to every face of the workspace.
 *
 * A document holds what never changes in a face (its definition, its asset and its embedding)
 * and its origin, which only becomes human. The searches return candidates only: their
 * person, their origin and their asset are read from the database by the caller, so that a
 * document left behind (a face deleted by a DQL query, an asset deleted) is never trusted.
 */
final readonly class FaceIndex
{
    /**
     * The nearest faces fetched when recognizing a face, of any origin and named by a user.
     */
    private const int NEAREST = 100;

    /**
     * The most similar faces a search returns (the max_result_window of Elasticsearch).
     */
    private const int MAX_SIMILAR = 10000;

    /**
     * Elasticsearch compares in float32 and the caller in float64: the threshold is checked by
     * the caller, Elasticsearch only has to keep the faces around it.
     */
    private const float THRESHOLD_MARGIN = 0.001;

    public function __construct(
        #[Autowire(service: 'fos_elastica.index.face')]
        private Index $index,
        private IndexPersister $indexPersister,
        private FaceIndexPersister $facePersister,
    ) {
    }

    /**
     * Writes the faces and removes the deleted ones, searchable as soon as it returns: the
     * next detection must recognize them (the photos of an event are processed in a row).
     *
     * Before the index exists (populate), the writes are only journaled and nothing is
     * refreshed: it runs after the flush of the faces named or deleted by users, whose request
     * must not fail on it.
     *
     * @param Attribute[] $faces
     * @param string[]    $deletedIds
     */
    public function update(array $faces, array $deletedIds = []): void
    {
        if (empty($faces) && empty($deletedIds)) {
            return;
        }

        if (!empty($faces)) {
            $this->indexPersister->replaceMany(Attribute::class, $faces);
        }
        if (!empty($deletedIds)) {
            $this->indexPersister->deleteManyByIdentifiers(Attribute::class, array_values($deletedIds));
        }
        if ($this->facePersister->indexExists()) {
            $this->index->refresh();
        }
    }

    /**
     * The candidates to recognize a face: its nearest faces at least $threshold similar, and
     * its nearest faces named by a user (which the recognized faces of their person may
     * outnumber).
     *
     * @param float[] $vector
     *
     * @return string[] face IDs
     */
    public function findNearest(string $definitionId, array $vector, float $threshold, ?string $excludeAssetId = null): array
    {
        $named = $this->createFilter($definitionId, $excludeAssetId);
        $named->addFilter(new Query\Term(['origin' => Attribute::ORIGIN_HUMAN]));

        $bool = new Query\BoolQuery();
        $bool->addShould($this->createKnn($vector, $threshold, self::NEAREST, $this->createFilter($definitionId, $excludeAssetId)));
        $bool->addShould($this->createKnn($vector, $threshold, self::NEAREST, $named));

        return $this->search($bool, 2 * self::NEAREST);
    }

    /**
     * The faces no user named that are at least $threshold similar, null when there are too
     * many of them for a search.
     *
     * @param float[] $vector
     *
     * @return string[]|null face IDs
     */
    public function findSimilarMachineFaces(string $definitionId, array $vector, float $threshold, ?string $excludeAssetId = null): ?array
    {
        $filter = $this->createFilter($definitionId, $excludeAssetId);
        $filter->addMustNot(new Query\Term(['origin' => Attribute::ORIGIN_HUMAN]));

        $ids = $this->search($this->createKnn($vector, $threshold, self::MAX_SIMILAR, $filter), self::MAX_SIMILAR);

        return count($ids) < self::MAX_SIMILAR ? $ids : null;
    }

    private function createFilter(string $definitionId, ?string $excludeAssetId): Query\BoolQuery
    {
        $filter = new Query\BoolQuery();
        $filter->addFilter(new Query\Term(['definitionId' => $definitionId]));
        if (null !== $excludeAssetId) {
            $filter->addMustNot(new Query\Term(['assetId' => $excludeAssetId]));
        }

        return $filter;
    }

    /**
     * @param float[] $vector
     */
    private function createKnn(array $vector, float $threshold, int $k, Query\BoolQuery $filter): Knn
    {
        $knn = new Knn('embedding', array_values(array_map('floatval', $vector)), min(self::MAX_SIMILAR, 2 * $k));
        $knn->setK($k);
        $knn->setSimilarity($threshold - self::THRESHOLD_MARGIN);
        $knn->setFilter($filter);

        return $knn;
    }

    /**
     * @return string[]
     */
    private function search(Query\AbstractQuery $query, int $size): array
    {
        $search = new Query($query);
        $search->setSize($size);
        $search->setSource(false);

        return array_map(
            static fn ($result): string => (string) $result->getId(),
            $this->index->search($search)->getResults(),
        );
    }
}
