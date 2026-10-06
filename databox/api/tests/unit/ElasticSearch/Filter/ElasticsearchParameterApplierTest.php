<?php

declare(strict_types=1);

namespace App\Tests\Unit\ElasticSearch\Filter;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use App\Elasticsearch\Filter\ElasticsearchFilterInterface;
use App\Elasticsearch\Filter\ElasticsearchParameterApplier;
use App\Elasticsearch\Filter\SearchQuery;
use Elastica\Query;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ElasticsearchParameterApplierTest extends TestCase
{
    public function testAppliesInstanceAndLocatedFiltersWithAValue(): void
    {
        $instanceFilter = new SpyElasticsearchFilter();
        $locatedFilter = new SpyElasticsearchFilter();
        $operation = (new GetCollection())->withParameters(new Parameters([
            'a' => (new QueryParameter(key: 'a', filter: $instanceFilter, property: 'propA'))->setValue('x'),
            'b' => (new QueryParameter(key: 'b', filter: 'located_filter'))->setValue(['y', 'z']),
            'missing' => new QueryParameter(key: 'missing', filter: $instanceFilter),
            'nullValue' => (new QueryParameter(key: 'nullValue', filter: $instanceFilter))->setValue(null),
            'noFilter' => (new QueryParameter(key: 'noFilter'))->setValue('ignored'),
            'ormOnly' => (new QueryParameter(key: 'ormOnly', filter: 'orm_only'))->setValue('ignored'),
        ]));

        $query = new SearchQuery(new Query\BoolQuery());
        $this->createApplier(['located_filter' => $locatedFilter, 'orm_only' => new \stdClass()])
            ->apply($query, 'App\Entity\Foo', $operation, ['extra' => 1]);

        $this->assertSame([['propA' => 'x']], array_column($instanceFilter->calls, 'filters'));
        $this->assertSame([['b' => ['y', 'z']]], array_column($locatedFilter->calls, 'filters'));
        $this->assertSame(1, $instanceFilter->calls[0]['extra']);
        $this->assertSame('a', $instanceFilter->calls[0]['parameter']->getKey());
        $this->assertSame($operation, $instanceFilter->calls[0]['operation']);
    }

    public function testNullOperationAppliesNothing(): void
    {
        $query = new SearchQuery(new Query\BoolQuery());
        $this->createApplier([])->apply($query, 'App\Entity\Foo', null);

        $this->assertEmpty((array) $query->bool->toArray()['bool']);
    }

    private function createApplier(array $services): ElasticsearchParameterApplier
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturnCallback(static fn (string $id): bool => isset($services[$id]));
        $locator->method('get')->willReturnCallback(static fn (string $id): object => $services[$id]);

        return new ElasticsearchParameterApplier($locator);
    }
}

final class SpyElasticsearchFilter implements ElasticsearchFilterInterface
{
    public array $calls = [];

    public function applyToElasticsearch(SearchQuery $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->calls[] = $context + ['operation' => $operation];
    }
}
