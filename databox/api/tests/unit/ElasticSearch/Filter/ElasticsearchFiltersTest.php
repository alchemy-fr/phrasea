<?php

declare(strict_types=1);

namespace App\Tests\Unit\ElasticSearch\Filter;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Filter\ExactSearchFilter;
use App\Api\Filter\InWorkspacesFilter;
use App\Api\Filter\PartialSearchFilter;
use App\Api\Filter\SortFilter;
use App\Elasticsearch\Filter\ElasticsearchFilterInterface;
use App\Elasticsearch\Filter\SearchQuery;
use App\Elasticsearch\Filter\SuggestQueryFilter;
use App\Entity\Core\Workspace;
use Elastica\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ElasticsearchFiltersTest extends TestCase
{
    private const string WS_A = '0199c0a0-0000-7000-8000-000000000001';
    private const string WS_B = '0199c0a0-0000-7000-8000-000000000002';

    public function testExactSearchFilterResolvesIrisAndIdsIntoATermsQuery(): void
    {
        $workspace = new Workspace();
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->method('getResourceFromIri')->willReturnCallback(static function (string $iri) use ($workspace): object {
            if ('/workspaces/'.self::WS_A === $iri) {
                return $workspace;
            }

            throw new InvalidArgumentException('Not an IRI');
        });

        $query = $this->apply(
            new ExactSearchFilter($iriConverter),
            new QueryParameter(key: 'workspace', property: 'workspace', extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'workspaceId']),
            ['/workspaces/'.self::WS_A, self::WS_B, 'between' => 'ignored'],
        );

        $this->assertSame([
            'bool' => ['filter' => [['terms' => ['workspaceId' => [$workspace->getId(), self::WS_B]]]]],
        ], $query->bool->toArray());
    }

    public function testExactSearchFilterIgnoresAnEmptyValue(): void
    {
        $query = $this->apply(
            new ExactSearchFilter($this->createMock(IriConverterInterface::class)),
            new QueryParameter(key: 'list', property: 'list'),
            [],
        );

        $this->assertEmpty((array) $query->bool->toArray()['bool']);
    }

    public function testInWorkspacesFilterAcceptsAListOrACommaSeparatedString(): void
    {
        $parameter = new QueryParameter(key: 'workspaceIds', extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'workspaceId']);
        $expected = ['bool' => ['filter' => [['terms' => ['workspaceId' => [self::WS_A, self::WS_B]]]]]];

        $this->assertSame($expected, $this->apply(new InWorkspacesFilter(), $parameter, [self::WS_A, self::WS_B])->bool->toArray());
        $this->assertSame($expected, $this->apply(new InWorkspacesFilter(), $parameter, self::WS_A.','.self::WS_B)->bool->toArray());

        $this->expectException(BadRequestHttpException::class);
        $this->apply(new InWorkspacesFilter(), $parameter, 'not-a-uuid');
    }

    public function testPartialSearchFilterBuildsACaseInsensitiveWildcard(): void
    {
        $parameter = new QueryParameter(key: 'value', property: 'value', extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'value.raw']);

        $this->assertSame([
            'bool' => ['filter' => [['wildcard' => ['value.raw' => ['value' => '*Da\\*rk*', 'boost' => 1.0, 'case_insensitive' => true]]]]],
        ], $this->apply(new PartialSearchFilter(), $parameter, 'Da*rk')->bool->toArray());

        $several = $this->apply(new PartialSearchFilter(), $parameter, ['a', 'b'])->bool->toArray();
        $this->assertCount(2, $several['bool']['filter'][0]['bool']['should']);
        $this->assertSame(1, $several['bool']['filter'][0]['bool']['minimum_should_match']);

        $this->assertEmpty((array) $this->apply(new PartialSearchFilter(), $parameter, ['gte' => 'operator map'])->bool->toArray()['bool']);
        $this->assertEmpty((array) $this->apply(new PartialSearchFilter(), $parameter, '')->bool->toArray()['bool']);
    }

    public function testSortFilterAddsASortClauseOnTheEsField(): void
    {
        $parameter = new QueryParameter(key: 'order[value]', property: 'value', extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'value.raw']);

        $this->assertSame([['value.raw' => 'desc']], $this->apply(new SortFilter(), $parameter, 'DESC')->getSort());
        $this->assertFalse($this->apply(new SortFilter(), $parameter, 'sideways')->hasSort());
        $this->assertFalse($this->apply(new SortFilter(), $parameter, ['asc'])->hasSort());
    }

    public function testSuggestQueryFilterMatchesTheSearchAsYouTypeSubFields(): void
    {
        $parameter = new QueryParameter(key: 'query', extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'name']);

        $this->assertSame([
            'bool' => ['must' => [['multi_match' => [
                'query' => 'nat',
                'type' => 'bool_prefix',
                'fields' => ['name.suggest', 'name.suggest._2gram', 'name.suggest._3gram'],
            ]]]],
        ], $this->apply(new SuggestQueryFilter(), $parameter, ' nat ')->bool->toArray());

        $this->assertEmpty((array) $this->apply(new SuggestQueryFilter(), $parameter, '  ')->bool->toArray()['bool']);
    }

    private function apply(ElasticsearchFilterInterface $filter, QueryParameter $parameter, mixed $value): SearchQuery
    {
        $parameter->setValue($value);
        $query = new SearchQuery(new Query\BoolQuery());
        $filter->applyToElasticsearch($query, 'App\Entity\Foo', null, [
            'filters' => [$parameter->getProperty() ?? $parameter->getKey() => $value],
            'parameter' => $parameter,
        ]);

        return $query;
    }
}
