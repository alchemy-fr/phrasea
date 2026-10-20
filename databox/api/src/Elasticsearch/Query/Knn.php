<?php

declare(strict_types=1);

namespace App\Elasticsearch\Query;

use Elastica\Query\AbstractQuery;

/**
 * kNN search query (ES >= 8.12), not provided by Elastica.
 */
class Knn extends AbstractQuery
{
    /**
     * @param float[] $queryVector
     */
    public function __construct(string $field, array $queryVector, int $numCandidates = 100)
    {
        $this->setParam('field', $field);
        $this->setParam('query_vector', $queryVector);
        $this->setParam('num_candidates', $numCandidates);
    }

    public function setFilter(AbstractQuery $filter): self
    {
        return $this->setParam('filter', $filter);
    }

    public function setSimilarity(float $similarity): self
    {
        return $this->setParam('similarity', $similarity);
    }
}
