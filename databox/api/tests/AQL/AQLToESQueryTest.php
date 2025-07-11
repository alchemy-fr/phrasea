<?php

namespace App\Tests\AQL;

use App\Attribute\Type\DateAttributeType;
use App\Attribute\Type\GeoPointAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AQL\DateNormalizer;
use App\Elasticsearch\AQL\Function\AQLFunctionRegistry;
use App\Elasticsearch\Facet\CreatedAtFacet;
use App\Elasticsearch\Facet\FacetRegistry;
use App\Elasticsearch\Facet\WorkspaceFacet;
use App\Tests\Attribute\Type\AttributeTypeRegistyTestFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AQLToESQueryTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testAQLToQuery(string $expression, string|array $expectedQuery, ?string $locale = null): void
    {
        $parser = new AQLParser();
        $result = $parser->parse($expression);
        $em = $this->createMock(EntityManagerInterface::class);

        $functionRegistry = new AQLFunctionRegistry();
        $functionRegistry->register(new MockNowFunction());

        $attributeTypeRegistry = AttributeTypeRegistyTestFactory::create();

        $esQueryConverter = new AQLToESQuery(
            new FacetRegistry([
                '@workspace' => new WorkspaceFacet($em),
                '@createdAt' => new CreatedAtFacet(),
            ]), $functionRegistry, $attributeTypeRegistry, new DateNormalizer());

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

    public function getCases(): array
    {
        return [
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
            ['@createdAt<="2025-01-16"', [
                'range' => ['createdAt' => [
                    'lte' => '2025-01-16',
                ]],
            ]],
            ['@createdAt<= (42) - 5 - 5', [
                'range' => ['createdAt' => [
                    'lte' => 42 - 5 - 5,
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
                    'gt' => MockNowFunction::VALUE * 8 - 3,
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
                    'gt' => MockNowFunction::VALUE + 3600,
                ]],
            ]],
            ['@createdAt > DATE_SUB(NOW(), "PT1M")', [
                'range' => ['createdAt' => [
                    'gt' => MockNowFunction::VALUE - 60,
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
