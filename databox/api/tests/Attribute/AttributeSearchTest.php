<?php

namespace App\Tests\Attribute;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Attribute\AttributeInterface;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AQL\DateNormalizer;
use App\Elasticsearch\AQL\Function\AQLFunctionRegistry;
use App\Elasticsearch\AttributeSearch;
use App\Elasticsearch\BuiltInField\BuiltInAttributeRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Tests\Attribute\Type\AttributeTypeRegistyTestFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class AttributeSearchTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testAttributeClustering(array $definitions, array $expectedClusters): void
    {
        $attributeTypeRegistry = AttributeTypeRegistyTestFactory::create();
        $builtInFieldRegistry = new BuiltInAttributeRegistry(new ServiceLocator([]));

        $fieldNameResolver = new FieldNameResolver(
            $attributeTypeRegistry,
            $builtInFieldRegistry
        );

        $aqlParser = new AQLParser();
        $aqlToESQuery = new AQLToESQuery(
            $builtInFieldRegistry,
            new AQLFunctionRegistry(),
            $attributeTypeRegistry,
            new DateNormalizer(),
        );

        $as = new AttributeSearch(
            $fieldNameResolver,
            $this->createMock(EntityManagerInterface::class),
            $attributeTypeRegistry,
            $aqlParser,
            $aqlToESQuery,
            new TemporaryCacheFactory(),
        );

        $clusters = $as->createClustersFromDefinitions($definitions);

        foreach ($expectedClusters as &$expectedCluster) {
            if (isset($expectedCluster['fields'])) {
                foreach ($expectedCluster['fields'] as &$field) {
                    if (isset($field['type'])) {
                        $field['type'] = $attributeTypeRegistry->getStrictType($field['type']);
                    }
                }
            }
        }

        $this->assertEquals($expectedClusters, $clusters);
    }

    public function getCases(): array
    {
        $createField = function (
            bool $allowed,
            string $wsId,
            string $slug,
            ?int $boost = null,
            $type = TextAttributeType::NAME,
            bool $multiple = false,
            bool $translatable = false,
        ): array {
            return [
                'allowed' => $allowed,
                'slug' => $slug,
                'type' => $type,
                'multiple' => $multiple,
                'workspaceId' => $wsId,
                'searchBoost' => $boost,
                'translatable' => $translatable,
                'enabledLocales' => [],
            ];
        };

        $defaultNameCluster = [
            'fields' => [
                'name' => [
                    'type' => TextAttributeType::NAME,
                    'b' => 1,
                ],
            ],
            'w' => null,
            'b' => 1,
            'locales' => [],
        ];

        return [
            [
                [],
                [
                    $defaultNameCluster,
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'name'),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.name_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                            'name' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                        'locales' => [],
                    ],
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'name', 42),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.name_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 42,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 42,
                        'locales' => [],
                    ],
                    [
                        'fields' => [
                            'name' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                        'locales' => [],
                    ],
                ],
            ],

            [
                [
                    $createField(false, 'w1', 'name', 42),
                ],
                [
                    $defaultNameCluster,
                ],
            ],

            [
                [
                    $createField(false, 'w1', 'name'),
                ],
                [
                    $defaultNameCluster,
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'name'),
                    $createField(true, 'w1', 'desc'),
                    $createField(true, 'w2', 'name'),
                    $createField(true, 'w2', 'desc'),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.name_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                            'name' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                        'locales' => [],
                    ],
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'name', 1),
                    $createField(true, 'w1', 'desc', 1),
                    $createField(true, 'w2', 'name', 1),
                    $createField(true, 'w2', 'desc', 2),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.name_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                            'name' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                        'locales' => [],
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 1,
                        'locales' => [],
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 2,
                            ],
                        ],
                        'w' => ['w2'],
                        'b' => 2,
                        'locales' => [],
                    ],
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'name'),
                    $createField(false, 'w1', 'desc'),
                    $createField(true, 'w2', 'name'),
                    $createField(true, 'w2', 'desc'),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.name_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                            'name' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                        'locales' => [],
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => ['w2'],
                        'b' => 1,
                        'locales' => [],
                    ],
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'name'),
                    $createField(true, 'w1', 'desc'),
                    $createField(true, 'w2', 'name'),
                    $createField(false, 'w2', 'desc'),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.name_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                            'name' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                        'locales' => [],
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 1,
                        'locales' => [],
                    ],
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'name'),
                    $createField(true, 'w1', 'desc'),
                    $createField(false, 'w2', 'name'),
                    $createField(false, 'w2', 'desc', 2),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.name_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 1,
                        'locales' => [],
                    ],
                    $defaultNameCluster,
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'name'),
                    $createField(true, 'w1', 'desc'),
                    $createField(true, 'w2', 'name'),
                    $createField(true, 'w2', 'desc', 2),
                    $createField(true, 'w3', 'desc', 2),
                    $createField(true, 'w4', 'name', 3),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.name_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => ['w1', 'w2'],
                        'b' => 1,
                        'locales' => [],
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.name_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 3,
                            ],
                        ],
                        'w' => ['w4'],
                        'b' => 3,
                        'locales' => [],
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 1,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 1,
                        'locales' => [],
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'type' => TextAttributeType::NAME,
                                'b' => 2,
                            ],
                        ],
                        'w' => ['w2', 'w3'],
                        'b' => 2,
                        'locales' => [],
                    ],
                    $defaultNameCluster,
                ],
            ],
        ];
    }
}
