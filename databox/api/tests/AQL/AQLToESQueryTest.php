<?php

declare(strict_types=1);

namespace App\Tests\AQL;

use App\Attribute\Type\BooleanAttributeType;
use App\Attribute\Type\DateAttributeType;
use App\Attribute\Type\GeoPointAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AbstractSearch;
use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AQL\DateNormalizer;
use App\Elasticsearch\AQL\Function\AQLFunctionRegistry;
use App\Elasticsearch\BuiltInAttribute\AssetStatusBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use App\Elasticsearch\BuiltInAttribute\CollectionBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\CreatedAtBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\DeletedBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\DirectCollectionBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\WorkspaceBuiltInAttribute;
use App\Entity\Core\Collection;
use App\Tests\Attribute\Type\AttributeTypeRegistryTestFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AQLToESQueryTest extends TestCase
{
    /**
     * Root collection.
     */
    private const string COLL_A = '2b2c8b1e-0000-4000-8000-00000000000a';

    /**
     * Child of {@see self::COLL_A}.
     */
    private const string COLL_B = '2b2c8b1e-0000-4000-8000-00000000000b';

    private const array COLLECTION_PATHS = [
        self::COLL_A => '/'.self::COLL_A,
        self::COLL_B => '/'.self::COLL_A.'/'.self::COLL_B,
    ];

    /**
     * @dataProvider getCases
     */
    public function testAQLToQuery(string $expression, string|array $expectedQuery, ?string $locale = null): void
    {
        $parser = new AQLParser();
        $result = $parser->parse($expression);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(fn (string $class, mixed $id): ?Collection => $this->createCollectionStub($class, $id));
        $translator = $this->createMock(TranslatorInterface::class);
        $security = $this->createMock(Security::class);

        $functionRegistry = new AQLFunctionRegistry();
        $functionRegistry->register(new MockNowFunction());

        $attributeTypeRegistry = AttributeTypeRegistryTestFactory::create();

        $container = new class([WorkspaceBuiltInAttribute::getKey() => fn () => new WorkspaceBuiltInAttribute($em), AssetStatusBuiltInAttribute::getKey() => fn () => new AssetStatusBuiltInAttribute($translator), DeletedBuiltInAttribute::getKey() => fn () => new DeletedBuiltInAttribute(), CreatedAtBuiltInAttribute::getKey() => fn () => new CreatedAtBuiltInAttribute(), CollectionBuiltInAttribute::getKey() => fn () => new CollectionBuiltInAttribute($em, $security), DirectCollectionBuiltInAttribute::getKey() => fn () => new DirectCollectionBuiltInAttribute($em, $security)]) implements ServiceProviderInterface {
            use ServiceLocatorTrait;
        };
        $builtInAttributeRegistry = new BuiltInAttributeRegistry($container);

        $esQueryConverter = new AQLToESQuery(
            $builtInAttributeRegistry, $functionRegistry, $attributeTypeRegistry, new DateNormalizer());

        $fieldClusters = [
            [
                'fields' => [
                    'attrs.{l}.foo_text_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(TextAttributeType::NAME),
                    ],
                    'attrs.{l}.field_text_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(TextAttributeType::NAME),
                    ],
                    'attrs._.number_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs._.othernumber_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs._.n0_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs._.n1_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs._.n2_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs._.n3_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs.{l}.hybrid_text_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(TextAttributeType::NAME),
                    ],
                    'attrs._.location_geo-point_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(GeoPointAttributeType::NAME),
                    ],
                    'attrs._.date_date_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(DateAttributeType::NAME),
                    ],
                    'attrs._.bool_boolean_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(BooleanAttributeType::NAME),
                    ],
                ],
                'w' => [],
                'locales' => ['it', 'de'],
            ],
            [
                'fields' => [
                    'attrs.{l}.www_text_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(TextAttributeType::NAME),
                    ],
                    'attrs.{l}.hybrid_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                ],
                'w' => ['4242'],
                'locales' => ['fr'],
            ],
        ];

        if (is_string($expectedQuery)) {
            $this->expectExceptionMessage($expectedQuery);
        } else {
            $this->assertIsArray($result, 'Parse error');
        }

        $query = $esQueryConverter->createQuery($fieldClusters, $result['data'], [
            'locale' => $locale,
        ])->toArray();
        if (!is_string($expectedQuery)) {
            $this->assertEquals($expectedQuery, $query);
        }
    }

    private function createCollectionStub(string $class, mixed $id): ?Collection
    {
        if (Collection::class !== $class || !isset(self::COLLECTION_PATHS[$id])) {
            return null;
        }

        $collection = $this->createMock(Collection::class);
        $collection->method('getAbsolutePath')->willReturn(self::COLLECTION_PATHS[$id]);

        return $collection;
    }

    public function getCases(): array
    {
        return [
            // @collection is recursive: the "collectionPaths" field is analyzed
            // with a path_hierarchy tokenizer, so descendants match too.
            ['@collection = "'.self::COLL_A.'"', [
                'term' => ['collectionPaths' => '/'.self::COLL_A],
            ]],
            ['@collection IN ("'.self::COLL_A.'", "'.self::COLL_B.'")', [
                'terms' => ['collectionPaths' => [
                    '/'.self::COLL_A,
                    '/'.self::COLL_A.'/'.self::COLL_B,
                ]],
            ]],
            // @directCollection targets the raw keyword sub field: only assets
            // directly attached to the given collections match.
            ['@directCollection = "'.self::COLL_A.'"', [
                'term' => ['collectionPaths.raw' => '/'.self::COLL_A],
            ]],
            ['@directCollection IN ("'.self::COLL_A.'", "'.self::COLL_B.'")', [
                'terms' => ['collectionPaths.raw' => [
                    '/'.self::COLL_A,
                    '/'.self::COLL_A.'/'.self::COLL_B,
                ]],
            ]],
            ['@directCollection NOT IN ("'.self::COLL_B.'")', [
                'bool' => [
                    'must_not' => [
                        ['terms' => ['collectionPaths.raw' => [
                            '/'.self::COLL_A.'/'.self::COLL_B,
                        ]]],
                    ],
                ],
            ]],
            ['@directCollection != "'.self::COLL_A.'"', [
                'bool' => [
                    'must_not' => [
                        ['term' => ['collectionPaths.raw' => '/'.self::COLL_A]],
                    ],
                ],
            ]],
            ['@directCollection EXISTS', [
                'exists' => ['field' => 'collectionPaths.raw'],
            ]],
            ['@directCollection = "not-an-uuid"', 'Invalid collection ID'],
            ['@directCollection = "2b2c8b1e-0000-4000-8000-0000000000ff"', 'Collection not found'],
            ['@directCollection CONTAINS "foo"', 'Operator "CONTAINS" not supported for field type "collection_path"'],
            ['date < "YYYY-88-88"', 'Invalid date value "YYYY-88-88"'],
            ['date < "9999-88-88"', [
                'range' => [
                    'attrs._.date_date_s' => ['lt' => '10006-06-27'],
                ],
            ]],
            ['date < ""', 'Invalid date value ""'],
            ['date < "2015"', [
                'range' => [
                    'attrs._.date_date_s' => ['lt' => 2015],
                ],
            ]],
            ['date=  "2015"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date=  "2015-05"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22 15"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22T15',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22T15"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22T15',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22T10:05"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22T10:05',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22T10:05:42"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22T10:05:42',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22 10:05:42"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22T10:05:42',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date !=  "2015"', [
                'bool' => [
                    'must_not' => [
                        [
                            'prefix' => [
                                'attrs._.date_date_s.raw' => [
                                    'value' => '2015',
                                    'boost' => 1.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['date !=  "2015-05"', [
                'bool' => [
                    'must_not' => [
                        [
                            'prefix' => [
                                'attrs._.date_date_s.raw' => [
                                    'value' => '2015-05',
                                    'boost' => 1.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['date !=  "2015-05-22"', [
                'bool' => [
                    'must_not' => [
                        [
                            'prefix' => [
                                'attrs._.date_date_s.raw' => [
                                    'value' => '2015-05-22',
                                    'boost' => 1.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['foo="bar"', [
                'term' => [
                    'attrs.fr.foo_text_s.raw' => 'bar',
                ],
            ], 'fr'],
            ['@workspace="42"', [
                'term' => ['workspaceId' => '42'],
            ]],
            ['@workspace=SUBSTRING("42aa", 0, 2)', [
                'term' => ['workspaceId' => '42'],
            ]],
            ['@workspace= NULL', [
                'term' => ['workspaceId' => null],
            ]],
            ['@workspace= null', [
                'term' => ['workspaceId' => null],
            ]],
            ['@deleted = true', [
                'bool' => [
                    'must' => [
                        [
                            'bool' => [
                                'minimum_should_match' => 1,
                                'should' => [
                                    ['term' => ['ownerId' => AbstractSearch::NO_AUTH]],
                                ],
                            ],
                        ],
                        ['bool' => [
                            'should' => [
                                ['term' => ['deleted' => true]],
                                ['term' => ['collectionDeleted' => true]],
                            ],
                        ]],
                    ],
                ],
            ]],
            ['@deleted = false', [
                'bool' => [
                    'must' => [
                        ['term' => ['deleted' => false]],
                        ['term' => ['collectionDeleted' => false]],
                    ],
                ],
            ]],
            ['@assetStatus IN (0, 1)', [
                'bool' => [
                    'minimum_should_match' => 1,
                    'should' => [
                        [
                            'bool' => [
                                'must' => [
                                    ['term' => ['status' => 0]],
                                ],
                            ],
                        ], [
                            'bool' => [
                                'must' => [
                                    ['term' => ['status' => 1]],
                                    ['term' => ['ownerId' => AbstractSearch::NO_AUTH]],
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['bool = null', [
                'bool' => [
                    'must_not' => [
                        [
                            'exists' => ['field' => 'attrs._.bool_boolean_s'],
                        ],
                    ],
                ],
            ]],
            ['bool = true', [
                'term' => ['attrs._.bool_boolean_s' => true],
            ]],
            ['bool = false', [
                'term' => ['attrs._.bool_boolean_s' => false],
            ]],
            ['@createdAt<="2025-01-16"', [
                'range' => ['createdAt' => [
                    'lte' => '2025-01-16',
                ]],
            ]],
            ['@createdAt<= (42) - 5 - 5', [
                'range' => ['createdAt' => [
                    'lte' => (42 - 5 - 5) * 1000,
                ]],
            ]],
            ['field IN (true, false)', [
                'bool' => [
                    'should' => [
                        ['terms' => ['attrs.it.field_text_s.raw' => [true, false]]],
                        ['terms' => ['attrs.de.field_text_s.raw' => [true, false]]],
                        ['terms' => ['attrs._.field_text_s.raw' => [true, false]]],
                    ],
                ],
            ]],
            ['field IN (true, null)', [
                'bool' => [
                    'should' => [
                        ['terms' => ['attrs.it.field_text_s.raw' => [true, null]]],
                        ['terms' => ['attrs.de.field_text_s.raw' => [true, null]]],
                        ['terms' => ['attrs._.field_text_s.raw' => [true, null]]],
                    ],
                ],
            ]],
            ['field IN (true, n1)', 'Unsupported operator "IN" in script conditions'],
            ['number > othernumber', [
                'script' => [
                    'script' => [
                        'source' => '(!doc["attrs._.number_number_s"].empty ? doc["attrs._.number_number_s"].value : null) > (!doc["attrs._.othernumber_number_s"].empty ? doc["attrs._.othernumber_number_s"].value : null)',
                    ],
                ],
            ]],
            ['number > othernumber * 2', [
                'script' => [
                    'script' => [
                        'source' => '(!doc["attrs._.number_number_s"].empty ? doc["attrs._.number_number_s"].value : null) > ((!doc["attrs._.othernumber_number_s"].empty ? doc["attrs._.othernumber_number_s"].value : null) * 2)',
                    ],
                ],
            ]],
            ['number > othernumber * (2 + 1)', [
                'script' => [
                    'script' => [
                        'source' => '(!doc["attrs._.number_number_s"].empty ? doc["attrs._.number_number_s"].value : null) > ((!doc["attrs._.othernumber_number_s"].empty ? doc["attrs._.othernumber_number_s"].value : null) * 3)',
                    ],
                ],
            ]],
            ['n0 > n1 * (n2 + n3)', [
                'script' => [
                    'script' => [
                        'source' => '(!doc["attrs._.n0_number_s"].empty ? doc["attrs._.n0_number_s"].value : null) > ((!doc["attrs._.n1_number_s"].empty ? doc["attrs._.n1_number_s"].value : null) * ((!doc["attrs._.n2_number_s"].empty ? doc["attrs._.n2_number_s"].value : null) + (!doc["attrs._.n3_number_s"].empty ? doc["attrs._.n3_number_s"].value : null)))',
                    ],
                ],
            ]],
            ['n0 > n1 * (n2 + n3)', [
                'script' => [
                    'script' => [
                        'source' => '(!doc["attrs._.n0_number_s"].empty ? doc["attrs._.n0_number_s"].value : null) > ((!doc["attrs._.n1_number_s"].empty ? doc["attrs._.n1_number_s"].value : null) * ((!doc["attrs._.n2_number_s"].empty ? doc["attrs._.n2_number_s"].value : null) + (!doc["attrs._.n3_number_s"].empty ? doc["attrs._.n3_number_s"].value : null)))',
                    ],
                ],
            ]],
            ['@createdAt > now() * 8 - 3', [
                'range' => ['createdAt' => [
                    'gt' => (MockNowFunction::VALUE * 8 - 3) * 1000,
                ]],
            ]],
            ['foo MATCHES SUBSTRING("hello", 1, 2)', [
                'multi_match' => [
                    'query' => 'el',
                    'fields' => ['attrs.*.foo_text_s'],
                ],
            ]],
            ['@createdAt > DATE_ADD(NOW(), "PT1H")', [
                'range' => ['createdAt' => [
                    'gt' => (MockNowFunction::VALUE + 3600) * 1000,
                ]],
            ]],
            ['@createdAt > DATE_SUB(NOW(), "PT1M")', [
                'range' => ['createdAt' => [
                    'gt' => (MockNowFunction::VALUE - 60) * 1000,
                ]],
            ]],
            ['foo = SUBSTRING(foo, 1, 2)', [
                'script' => [
                    'script' => ['source' => '(!doc["attrs.{l}.foo_text_s"].empty ? doc["attrs.{l}.foo_text_s"].value : null) = (!doc["attrs.{l}.foo_text_s"].empty ? doc["attrs.{l}.foo_text_s"].value : null).Substring(1, 2)'],
                ],
            ]],
            ['www = SUBSTRING(foo, 1, 2)', [
                'bool' => [
                    'must' => [
                        [
                            'script' => [
                                'script' => ['source' => '(!doc["attrs.{l}.www_text_s"].empty ? doc["attrs.{l}.www_text_s"].value : null) = (!doc["attrs.{l}.foo_text_s"].empty ? doc["attrs.{l}.foo_text_s"].value : null).Substring(1, 2)'],
                            ],
                        ],
                        [
                            'terms' => [
                                'workspaceId' => ['4242'],
                            ],
                        ],
                    ],
                ],
            ]],
            ['www = SUBSTRING(hybrid, 1, 2)', [
                'bool' => [
                    'minimum_should_match' => 1,
                    'should' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'script' => [
                                            'script' => ['source' => '(!doc["attrs.{l}.www_text_s"].empty ? doc["attrs.{l}.www_text_s"].value : null) = (!doc["attrs.{l}.hybrid_text_s"].empty ? doc["attrs.{l}.hybrid_text_s"].value : null).Substring(1, 2)'],
                                        ],
                                    ],
                                    [
                                        'terms' => [
                                            'workspaceId' => ['4242'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'script' => [
                                            'script' => ['source' => '(!doc["attrs.{l}.www_text_s"].empty ? doc["attrs.{l}.www_text_s"].value : null) = (!doc["attrs.{l}.hybrid_number_s"].empty ? doc["attrs.{l}.hybrid_number_s"].value : null).Substring(1, 2)'],
                                        ],
                                    ],
                                    [
                                        'terms' => [
                                            'workspaceId' => ['4242'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['foo = SUBSTRING(hybrid, 1, 2)', [
                'bool' => [
                    'minimum_should_match' => 1,
                    'should' => [
                        [
                            'script' => [
                                'script' => ['source' => '(!doc["attrs.{l}.foo_text_s"].empty ? doc["attrs.{l}.foo_text_s"].value : null) = (!doc["attrs.{l}.hybrid_text_s"].empty ? doc["attrs.{l}.hybrid_text_s"].value : null).Substring(1, 2)'],
                            ],
                        ],
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'script' => [
                                            'script' => ['source' => '(!doc["attrs.{l}.foo_text_s"].empty ? doc["attrs.{l}.foo_text_s"].value : null) = (!doc["attrs.{l}.hybrid_number_s"].empty ? doc["attrs.{l}.hybrid_number_s"].value : null).Substring(1, 2)'],
                                        ],
                                    ],
                                    [
                                        'terms' => [
                                            'workspaceId' => ['4242'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['hybrid = SUBSTRING(foo, 1, 2)', [
                'bool' => [
                    'minimum_should_match' => 1,
                    'should' => [
                        [
                            'script' => [
                                'script' => ['source' => '(!doc["attrs.{l}.hybrid_text_s"].empty ? doc["attrs.{l}.hybrid_text_s"].value : null) = (!doc["attrs.{l}.foo_text_s"].empty ? doc["attrs.{l}.foo_text_s"].value : null).Substring(1, 2)'],
                            ],
                        ],
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'script' => [
                                            'script' => ['source' => '(!doc["attrs.{l}.hybrid_number_s"].empty ? doc["attrs.{l}.hybrid_number_s"].value : null) = (!doc["attrs.{l}.foo_text_s"].empty ? doc["attrs.{l}.foo_text_s"].value : null).Substring(1, 2)'],
                                        ],
                                    ],
                                    [
                                        'terms' => [
                                            'workspaceId' => ['4242'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            [
                'location WITHIN CIRCLE (48.8, 2.32, "10km")',
                [
                    'geo_distance' => [
                        'distance' => '10km',
                        'attrs._.location_geo-point_s' => [
                            'lat' => 48.8,
                            'lon' => 2.32,
                        ],
                    ],
                ],
            ],
            [
                'location WITHIN RECTANGLE (1.1, 1.2, 2.1, 2.2)',
                [
                    'geo_bounding_box' => [
                        'attrs._.location_geo-point_s' => [
                            'top_left' => ['lat' => 1.1, 'lon' => 1.2],
                            'bottom_right' => ['lat' => 2.1, 'lon' => 2.2],
                        ],
                    ],
                ],
            ],
            [
                'date CONTAINS "2023-10-01"',
                'Operator "CONTAINS" not supported for field type "date"',
            ],
            [
                'date START WITH "2023-10-01"',
                'Operator "STARTS_WITH" not supported for field type "date"',
            ],
        ];
    }
}
