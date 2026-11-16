<?php

declare(strict_types=1);

namespace App\Tests\Unit\Attribute;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Attribute\AttributeInterface;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AQL\DateNormalizer;
use App\Elasticsearch\AQL\Function\AQLFunctionRegistry;
use App\Elasticsearch\AttributeSearch;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Tests\Unit\Attribute\Type\AttributeTypeRegistryTestFactory;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class AttributeSearchTest extends TestCase
{
    #[DataProvider('getCases')]
    public function testAttributeClustering(array $definitions, array $expectedClusters): void
    {
        $attributeTypeRegistry = AttributeTypeRegistryTestFactory::create();
        $as = $this->createAttributeSearch();

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

    public function testReadableWorkspacesOfAKey(): void
    {
        $as = $this->createAttributeSearch([
            self::createRow(true, 'w1', 'salary'),
            self::createRow(false, 'w2', 'salary', searchable: false),
            self::createRow(true, 'w1', 'city'),
            self::createRow(true, 'w2', 'city', searchable: false),
            self::createRow(false, 'w1', 'secret'),
            self::createRow(false, 'w2', 'secret'),
        ]);

        $this->assertSame(['w1'], $as->getReadableWorkspaceIds('u', [], 'salary_text_s'));
        $this->assertTrue($as->isReadableAttributeKey('u', [], 'salary_text_s'));
        $this->assertEquals(new Query\Terms('workspaceId', ['w1']), $as->createVisibilityQuery('u', [], 'salary_text_s'));

        $this->assertNull($as->getReadableWorkspaceIds('u', [], 'city_text_s'));
        $this->assertTrue($as->isReadableAttributeKey('u', [], 'city_text_s'));
        $this->assertNull($as->createVisibilityQuery('u', [], 'city_text_s'));

        $this->assertSame([], $as->getReadableWorkspaceIds('u', [], 'secret_text_s'));
        $this->assertFalse($as->isReadableAttributeKey('u', [], 'secret_text_s'));
        $this->assertEquals(new Query\MatchNone(), $as->createVisibilityQuery('u', [], 'secret_text_s'));

        $this->assertSame([], $as->getReadableWorkspaceIds('u', [], 'ghost_text_s'));
        $this->assertFalse($as->isReadableAttributeKey('u', [], 'ghost_text_s'));
        $this->assertEquals(new Query\MatchNone(), $as->createVisibilityQuery('u', [], 'ghost_text_s'));
    }

    /**
     * Every definition sharing a key writes into the same ES field, searchable or not.
     */
    public function testUnreadableNonSearchableDefinitionRestrictsTheSearchableOnes(): void
    {
        $as = $this->createAttributeSearch([
            self::createRow(true, 'w1', 'salary'),
            self::createRow(false, 'w2', 'salary', searchable: false),
            self::createRow(true, 'w1', 'city'),
            self::createRow(true, 'w2', 'city', searchable: false),
            self::createRow(true, 'w1', 'notes', searchable: false),
        ]);

        $fieldsByWorkspaces = [];
        foreach ($as->buildSearchableAttributeDefinitionsGroups('u', []) as $cluster) {
            foreach (array_keys($cluster['fields']) as $field) {
                $fieldsByWorkspaces[$field] = $cluster['w'];
            }
        }

        $this->assertSame([
            AttributeInterface::ATTRIBUTES_FIELD.'._.salary_text_s' => ['w1'],
            AttributeInterface::ATTRIBUTES_FIELD.'._.city_text_s' => null,
            'name' => null,
        ], $fieldsByWorkspaces);
    }

    private function createAttributeSearch(array $rows = []): AttributeSearch
    {
        $attributeTypeRegistry = AttributeTypeRegistryTestFactory::create();
        $builtInAttributeRegistry = new BuiltInAttributeRegistry(new ServiceLocator([]));

        $repository = $this->createStub(AttributeDefinitionRepository::class);
        $repository->method('getAttributesWithPermission')->willReturn($rows);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        return new AttributeSearch(
            new FieldNameResolver($attributeTypeRegistry, $builtInAttributeRegistry),
            $em,
            $attributeTypeRegistry,
            new AQLParser(),
            new AQLToESQuery(
                $builtInAttributeRegistry,
                new AQLFunctionRegistry(),
                $attributeTypeRegistry,
                new DateNormalizer(),
            ),
            new TemporaryCacheFactory(),
        );
    }

    private static function createRow(bool $allowed, string $wsId, string $slug, bool $searchable = true): array
    {
        return [
            'allowed' => $allowed,
            'searchable' => $searchable,
            'slug' => $slug,
            'type' => TextAttributeType::NAME,
            'multiple' => false,
            'workspaceId' => $wsId,
            'searchBoost' => null,
            'translatable' => false,
            'enabledLocales' => [],
        ];
    }

    public static function getCases(): array
    {
        $createField = (fn (bool $allowed, string $wsId, string $slug, ?int $boost = null, $type = TextAttributeType::NAME, bool $multiple = false, bool $translatable = false): array => [
            'allowed' => $allowed,
            'slug' => $slug,
            'type' => $type,
            'multiple' => $multiple,
            'workspaceId' => $wsId,
            'searchBoost' => $boost,
            'translatable' => $translatable,
            'enabledLocales' => [],
        ]);

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
