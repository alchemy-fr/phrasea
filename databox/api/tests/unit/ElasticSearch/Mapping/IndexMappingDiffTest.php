<?php

declare(strict_types=1);

namespace App\Tests\Unit\ElasticSearch\Mapping;

use App\Elasticsearch\Mapping\IndexMappingDiff;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IndexMappingDiffTest extends TestCase
{
    #[DataProvider('getCases')]
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

    #[DataProvider('getSettingsCases')]
    public function testShouldReindexOnSettings(bool $expected, array $indexedSettings, array $newSettings): void
    {
        $differ = new IndexMappingDiff();

        $mappings = [
            'properties' => [
                'title' => [
                    'type' => 'text',
                    'analyzer' => 'text',
                ],
            ],
        ];

        $this->assertSame($expected, $differ->shouldReindex(
            ['settings' => $indexedSettings, 'mappings' => $mappings],
            ['settings' => $newSettings, 'mappings' => $mappings],
        ));
    }

    public static function getSettingsCases(): array
    {
        $analysis = [
            'analyzer' => [
                'text' => [
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => ['lowercase'],
                ],
            ],
        ];
        $settings = [
            'index' => [
                'analysis' => $analysis,
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ];

        $changedAnalysis = $settings;
        $changedAnalysis['index']['analysis']['analyzer']['text']['filter'] = ['lowercase', 'asciifolding'];

        $changedOtherSettings = $settings;
        $changedOtherSettings['index']['number_of_shards'] = 3;
        $changedOtherSettings['index']['refresh_interval'] = '30s';

        return [
            'identical settings' => [false, $settings, $settings],
            'different analysis' => [true, $settings, $changedAnalysis],
            'other settings differ, same analysis' => [false, $settings, $changedOtherSettings],
        ];
    }

    public static function getCases(): array
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
