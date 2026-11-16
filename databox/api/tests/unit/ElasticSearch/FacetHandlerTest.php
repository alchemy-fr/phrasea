<?php

declare(strict_types=1);

namespace App\Tests\Unit\ElasticSearch;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use App\Elasticsearch\BuiltInAttribute\OwnerBuiltInAttribute;
use App\Elasticsearch\FacetHandler;
use App\Tests\Unit\Attribute\Type\AttributeTypeRegistryTestFactory;
use Elastica\Query;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Translation\TranslatorInterface;

class FacetHandlerTest extends TestCase
{
    public function testFilteredFacetsKeepTheFormatOfPlainOnes(): void
    {
        $handler = new FacetHandler(
            new BuiltInAttributeRegistry(new ServiceLocator([])),
            AttributeTypeRegistryTestFactory::create(),
            $this->createStub(TranslatorInterface::class),
        );

        $terms = [
            'meta' => ['displayName' => 'Salary'],
            'doc_count_error_upper_bound' => 0,
            'sum_other_doc_count' => 0,
            'buckets' => [['key' => '1000', 'doc_count' => 2]],
        ];

        $plain = $handler->normalizeBuckets([
            'salary_text_s' => $terms,
            'salary_text_s'.FacetHandler::MISSING_SUFFIX => ['doc_count' => 3],
        ]);
        $filtered = $handler->normalizeBuckets([
            'salary_text_s' => [
                'doc_count' => 5,
                FacetHandler::VISIBLE_AGG => $terms,
            ],
            'salary_text_s'.FacetHandler::MISSING_SUFFIX => [
                'doc_count' => 5,
                FacetHandler::VISIBLE_AGG => ['doc_count' => 3],
            ],
        ]);

        $this->assertSame([
            'salary_text_s' => [
                ...$terms,
                'missing_count' => 3,
            ],
        ], $plain);
        $this->assertSame($plain, $filtered);
    }

    /**
     * The @owner facet requires the role set in API_ASSET_OWNER_PROPERTY_REQUIRED_ROLE, if any:
     * an anonymous user has none.
     */
    #[DataProvider('getOwnerFacetCases')]
    public function testBuiltInFacetsSkipTheAttributesDisabledForTheUser(string $requiredRole, array $expectedAggregations): void
    {
        $owner = new OwnerBuiltInAttribute($this->createStub(UserRepositoryInterface::class), $requiredRole);
        $owner->setSecurity($this->createStub(Security::class));

        $handler = new FacetHandler(
            new BuiltInAttributeRegistry(new ServiceLocator([
                OwnerBuiltInAttribute::KEY => fn (): OwnerBuiltInAttribute => $owner,
            ])),
            AttributeTypeRegistryTestFactory::create(),
            $this->createStub(TranslatorInterface::class),
        );

        $query = new Query();
        $handler->addBuiltInFacets($query);

        $this->assertSame($expectedAggregations, array_keys($query->toArray()['aggs'] ?? []));
    }

    public static function getOwnerFacetCases(): iterable
    {
        yield 'no required role' => ['', [OwnerBuiltInAttribute::KEY, OwnerBuiltInAttribute::KEY.FacetHandler::MISSING_SUFFIX]];
        yield 'required role missing' => ['admin', []];
    }
}
