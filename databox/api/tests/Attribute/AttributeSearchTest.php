<?php

namespace App\Tests\Attribute;

use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AttributeSearch;
use App\Elasticsearch\Facet\FacetRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\SearchType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AttributeSearchTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testAttributeClustering(array $definitions, array $expectedClusters): void
    {
        $attributeTypeRegistry = new AttributeTypeRegistry([
            TextAttributeType::NAME => new TextAttributeType(),
        ]);
        $facetRegistry = new FacetRegistry([]);

        $fieldNameResolver = new FieldNameResolver(
            $attributeTypeRegistry,
            $facetRegistry
        );

        $as = new AttributeSearch(
            $fieldNameResolver,
            $this->createMock(EntityManagerInterface::class),
            $attributeTypeRegistry,
        );

        $clusters = $as->createClustersFromDefinitions($definitions);
        $this->assertEquals($expectedClusters, $clusters);
    }

    public function getCases(): array
    {
        $createField = function (
            bool $allowed,
            string $wsId,
            string $slug,
            ?int $boost = null,
            $fieldType = 'text',
            bool $multiple = false,
            bool $translatable = false,
        ): array {
            return [
                'allowed' => $allowed,
                'slug' => $slug,
                'fieldType' => $fieldType,
                'multiple' => $multiple,
                'workspaceId' => $wsId,
                'searchBoost' => $boost,
                'translatable' => $translatable,
            ];
        };

        $defaultTitleCluster = [
            'fields' => [
                'title' => [
                    'st' => SearchType::Match->value,
                    'b' => 1,
                    'fz' => true,
                ],
            ],
            'w' => null,
            'b' => 1,
        ];

        return [
            [
                [],
                [
                    $defaultTitleCluster,
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'title'),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.title_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                            'title' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                    ],
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'title', 42),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.title_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 42,
                                'fz' => true,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 42,
                    ],
                    [
                        'fields' => [
                            'title' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                    ],
                ],
            ],

            [
                [
                    $createField(false, 'w1', 'title', 42),
                ],
                [
                    $defaultTitleCluster,
                ],
            ],

            [
                [
                    $createField(false, 'w1', 'title'),
                ],
                [
                    $defaultTitleCluster,
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'title'),
                    $createField(true, 'w1', 'desc'),
                    $createField(true, 'w2', 'title'),
                    $createField(true, 'w2', 'desc'),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.title_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                            'title' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                    ],
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'title', 1),
                    $createField(true, 'w1', 'desc', 1),
                    $createField(true, 'w2', 'title', 1),
                    $createField(true, 'w2', 'desc', 2),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.title_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                            'title' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 2,
                                'fz' => true,
                            ],
                        ],
                        'w' => ['w2'],
                        'b' => 2,
                    ],
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'title'),
                    $createField(false, 'w1', 'desc'),
                    $createField(true, 'w2', 'title'),
                    $createField(true, 'w2', 'desc'),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.title_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                            'title' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => ['w2'],
                        'b' => 1,
                    ],
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'title'),
                    $createField(true, 'w1', 'desc'),
                    $createField(true, 'w2', 'title'),
                    $createField(false, 'w2', 'desc'),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.title_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                            'title' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 1,
                    ],
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'title'),
                    $createField(true, 'w1', 'desc'),
                    $createField(false, 'w2', 'title'),
                    $createField(false, 'w2', 'desc', 2),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.title_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 1,
                    ],
                    $defaultTitleCluster,
                ],
            ],

            [
                [
                    $createField(true, 'w1', 'title'),
                    $createField(true, 'w1', 'desc'),
                    $createField(true, 'w2', 'title'),
                    $createField(true, 'w2', 'desc', 2),
                    $createField(true, 'w3', 'desc', 2),
                    $createField(true, 'w4', 'title', 3),
                ],
                [
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.title_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => ['w1', 'w2'],
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.title_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 3,
                                'fz' => true,
                            ],
                        ],
                        'w' => ['w4'],
                        'b' => 3,
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 1,
                                'fz' => true,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            AttributeInterface::ATTRIBUTES_FIELD.'._.desc_text_s' => [
                                'st' => SearchType::Match->value,
                                'b' => 2,
                                'fz' => true,
                            ],
                        ],
                        'w' => ['w2', 'w3'],
                        'b' => 2,
                    ],
                    $defaultTitleCluster,
                ],
            ],
        ];
    }
}
