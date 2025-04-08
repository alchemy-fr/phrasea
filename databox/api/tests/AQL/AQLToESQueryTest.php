<?php

namespace App\Tests\AQL;

use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AQL\Function\AQLFunctionRegistry;
use App\Elasticsearch\Facet\CreatedAtFacet;
use App\Elasticsearch\Facet\FacetRegistry;
use App\Elasticsearch\Facet\WorkspaceFacet;
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

        $esQueryConverter = new AQLToESQuery(new FacetRegistry([
            '@workspace' => new WorkspaceFacet($em),
            '@createdAt' => new CreatedAtFacet(),
        ]), $functionRegistry);

        $fieldClusters = [
            [
                'fields' => [
                    'attrs.{l}.foo_text_s' => [
                        'raw' => null,
                    ],
                    'attrs.{l}.field_text_s' => [
                        'raw' => null,
                    ],
                    'attrs._.number_number_s' => [
                        'raw' => null,
                    ],
                    'attrs._.othernumber_number_s' => [
                        'raw' => null,
                    ],
                    'attrs._.n0_number_s' => [
                        'raw' => null,
                    ],
                    'attrs._.n1_number_s' => [
                        'raw' => null,
                    ],
                    'attrs._.n2_number_s' => [
                        'raw' => null,
                    ],
                    'attrs._.n3_number_s' => [
                        'raw' => null,
                    ],
                    'attrs.{l}.hybrid_text_s' => [
                        'raw' => null,
                    ],
                ],
                'w' => null,
                'locales' => ['it', 'de'],
            ],
            [
                'fields' => [
                    'attrs.{l}.www_text_s' => [
                        'raw' => null,
                    ],
                    'attrs.{l}.hybrid_number_s' => [
                        'raw' => null,
                    ],
                ],
                'w' => '4242',
                'locales' => ['fr'],
            ],
        ];

        if (is_string($expectedQuery)) {
            $this->expectExceptionMessage($expectedQuery);
        }

        $query = $esQueryConverter->createQuery($fieldClusters, $result['data'], [
            'locale' => $locale,
        ])->toArray();
        $this->assertEquals($expectedQuery, $query);
    }

    public function getCases(): array
    {
        return [
            ['foo="bar"', [
                'multi_match' => [
                    'query' => 'bar',
                    'fields' => ['attrs.*.foo_text_s'],
                ],
            ]],
            ['foo="bar"', [
                'term' => [
                    'attrs.fr.foo_text_s' => 'bar',
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
                        ['terms' => ['attrs.it.field_text_s' => [true, false]]],
                        ['terms' => ['attrs.de.field_text_s' => [true, false]]],
                        ['terms' => ['attrs._.field_text_s' => [true, false]]],
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
                            'term' => [
                                'workspaceId' => '4242',
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
                                        'term' => [
                                            'workspaceId' => '4242',
                                        ],
                                    ],
                                ]
                            ]
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
                                        'term' => [
                                            'workspaceId' => '4242',
                                        ],
                                    ],
                                ]
                            ]
                        ]
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
                                        'term' => [
                                            'workspaceId' => '4242',
                                        ],
                                    ],
                                ]
                            ]
                        ]
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
                                        'term' => [
                                            'workspaceId' => '4242',
                                        ],
                                    ],
                                ]
                            ]
                        ]
                    ],
                ],
            ]],
        ];
    }
}
