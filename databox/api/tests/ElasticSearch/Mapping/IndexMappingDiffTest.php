<?php

declare(strict_types=1);

namespace App\Tests\ElasticSearch\Mapping;

use App\Elasticsearch\Mapping\IndexMappingDiff;
use PHPUnit\Framework\TestCase;

class IndexMappingDiffTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testShouldReindex(bool $expected, array $indexedMapping, array $newMapping): void
    {
        $differ = new IndexMappingDiff();

        $indexedMappingWrapped = [
            'settings' => [
                'index' => [
                    'analysis' => [
                        'analyzer' => [
                            'text' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase'],
                            ],
                        ],
                    ],
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
            ],
            'mappings' => [
                'properties' => [
                    'attributes' => [
                        'properties' => $indexedMapping,
                    ],
                ],
            ],
        ];
        $newMappingWrapped = [
            'settings' => [
                'index' => [
                    'analysis' => [
                        'analyzer' => [
                            'text' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase'],
                            ],
                        ],
                    ],
                    'number_of_shards' => 2,
                    'number_of_replicas' => 0,
                ],
            ],
            'mappings' => [
                'properties' => [
                    'attributes' => [
                        'properties' => $newMapping,
                    ],
                ],
            ],
        ];
        $this->assertEquals($expected, $differ->shouldReindex($indexedMappingWrapped, $newMappingWrapped));
    }

    public function getCases(): array
    {
        $attributes = [
            'a' => [
                'type' => 'text',
                'analyzer' => 'text',
                'meta' => [
                    'attribute_id' => 'def_a',
                    'attribute_name' => 'name_a',
                ],
            ],
        ];
        $attributes2 = [
            'a' => [
                'type' => 'text',
                'meta' => [
                    'attribute_id' => 'def_a',
                    'attribute_name' => 'name_a',
                ],
                'fields' => [
                    'raw' => [
                        'type' => 'keyword',
                    ],
                ],
            ],
        ];

        return [
            [false, $attributes, $attributes],
            [true, $attributes, [
                'b' => [
                    'type' => 'text',
                    'analyzer' => 'text',
                    'meta' => [
                        'attribute_id' => 'def_a',
                        'attribute_name' => 'name_a',
                    ],
                ],
            ]],
            [true, $attributes, [
                'a' => [
                    'type' => 'text',
                    'meta' => [
                        'attribute_id' => 'def_a_CHANGED',
                        'attribute_name' => 'name_a',
                    ],
                ],
            ]],
            [false, $attributes, [
                'a' => [
                    'type' => 'text',
                    'analyzer' => 'text',
                    'meta' => [
                        'attribute_id' => 'def_a',
                        'attribute_name' => 'name_a_CHANGED',
                    ],
                ],
            ]],
            [true, $attributes, [
                'a' => [
                    'type' => 'text',
                    'meta' => [
                        'attribute_id' => 'def_a',
                        'attribute_name' => 'name_a_CHANGED',
                    ],
                ],
            ]],
            [true, $attributes, [
                'a' => [
                    'type' => 'text',
                    'analyzer' => 'text_CHANGED',
                    'meta' => [
                        'attribute_id' => 'def_a',
                        'attribute_name' => 'name_a',
                    ],
                ],
            ]],
            [true, $attributes, [
                'a' => [
                    'type' => 'text',
                    'meta' => [
                        'attribute_id' => 'def_a',
                        'attribute_name' => 'name_a',
                    ],
                    'fields' => [
                        'raw' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
            ]],
            [false, $attributes2, [
                'a' => [
                    'type' => 'text',
                    'meta' => [
                        'attribute_id' => 'def_a',
                        'attribute_name' => 'name_a',
                    ],
                    'fields' => [
                        'raw' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
            ]],
            [true, $attributes2, [
                'a' => [
                    'type' => 'text',
                    'meta' => [
                        'attribute_id' => 'def_a',
                        'attribute_name' => 'name_a',
                    ],
                    'fields' => [
                        'raw' => [
                            'type' => 'text',
                        ],
                    ],
                ],
            ]],
        ];
    }
}
