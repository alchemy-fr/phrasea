<?php

declare(strict_types=1);

namespace App\Tests\ElasticSearch;

use App\Elasticsearch\Query\Knn;
use Elastica\Query;
use PHPUnit\Framework\TestCase;

class KnnQueryTest extends TestCase
{
    public function testKnnQuerySerialization(): void
    {
        $knn = new Knn('embedding', [0.1, 0.2, 0.3], 100);

        $this->assertSame([
            'knn' => [
                'field' => 'embedding',
                'query_vector' => [0.1, 0.2, 0.3],
                'num_candidates' => 100,
            ],
        ], $knn->toArray());
    }

    public function testKnnQueryEmbedsAclFilter(): void
    {
        $filter = new Query\BoolQuery();
        $filter->addFilter(new Query\Term(['users' => '123']));
        $filter->addMustNot(new Query\Terms('_id', ['abc']));

        $knn = new Knn('embedding', [0.1], 10);
        $knn->setFilter($filter);

        $array = $knn->toArray();

        $this->assertSame([
            'bool' => [
                'filter' => [
                    ['term' => ['users' => '123']],
                ],
                'must_not' => [
                    ['terms' => ['_id' => ['abc']]],
                ],
            ],
        ], $array['knn']['filter']);
    }
}
