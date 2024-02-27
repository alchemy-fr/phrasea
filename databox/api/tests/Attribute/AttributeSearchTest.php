<?php

namespace App\Tests\Attribute;

use App\Asset\Attribute\AssetTitleResolver;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AttributeSearch;
use App\Elasticsearch\Facet\FacetRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
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
                    'st' => AttributeSearch::FIELD_MATCH,
                    'b' => 1,
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
                            'attributes._.title_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                            'title' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
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
                            'attributes._.title_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 42,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 42,
                    ],
                    [
                        'fields' => [
                            'title' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
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
                            'attributes._.title_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                            'attributes._.desc_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                            'title' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
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
                            'attributes._.title_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                            'title' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            'attributes._.desc_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            'attributes._.desc_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 2,
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
                            'attributes._.title_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                            'title' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            'attributes._.desc_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
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
                            'attributes._.title_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                            'title' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                        ],
                        'w' => null,
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            'attributes._.desc_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
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
                            'attributes._.title_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                            'attributes._.desc_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
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
                            'attributes._.title_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                        ],
                        'w' => ['w1', 'w2'],
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            'attributes._.title_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 3,
                            ],
                        ],
                        'w' => ['w4'],
                        'b' => 3,
                    ],
                    [
                        'fields' => [
                            'attributes._.desc_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 1,
                            ],
                        ],
                        'w' => ['w1'],
                        'b' => 1,
                    ],
                    [
                        'fields' => [
                            'attributes._.desc_text_s' => [
                                'st' => AttributeSearch::FIELD_MATCH,
                                'b' => 2,
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
