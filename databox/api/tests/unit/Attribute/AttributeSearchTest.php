<?php

declare(strict_types=1);

namespace App\Tests\Unit\Attribute;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Attribute\AttributeInterface;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AQL\AQLConditionResolver;
use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AQL\DateNormalizer;
use App\Elasticsearch\AQL\Function\AQLFunctionRegistry;
use App\Elasticsearch\AttributeSearch;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Repository\Core\AssetPolicyRepository;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\CollectionRepository;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;
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

    /**
     * Asset policies hide a definition like an unreadable one: in the whole workspace without
     * condition, otherwise on the assets under the collections of their conditions only.
     */
    public function testAssetPoliciesHideDefinitions(): void
    {
        $w1 = new Workspace();
        $w2 = new Workspace();
        $parent = new Collection();
        $child = new Collection();
        $child->setParent($parent);
        $unknownCollectionId = 'f1b4b4a8-0000-4000-8000-000000000000';

        $as = $this->createAttributeSearch([
            self::createRow(true, $w1->getId(), 'secret', id: 'secret-1'),
            self::createRow(true, $w2->getId(), 'secret', id: 'secret-2'),
            self::createRow(true, $w1->getId(), 'title', id: 'title-1'),
            self::createRow(true, $w2->getId(), 'title', id: 'title-2'),
            self::createRow(true, $w1->getId(), 'city', id: 'city-1'),
            self::createRow(true, $w1->getId(), 'notes', id: 'notes-1'),
        ], [
            self::createPolicy($w1, ['secret-1']),
            self::createPolicy($w1, ['title-1', 'city-1'], [
                ['field' => 'collection', 'operator' => '=', 'value' => $parent->getId()],
                ['field' => 'collection', 'operator' => '=', 'value' => $child->getId()],
            ]),
            // A policy only applies to the definitions of its workspace
            self::createPolicy($w2, ['notes-1']),
            // No asset is in a collection that does not exist
            self::createPolicy($w1, ['notes-1'], [['field' => 'collection', 'operator' => '=', 'value' => $unknownCollectionId]]),
        ], [$parent, $child]);

        $this->assertSame([$w2->getId()], $as->getReadableWorkspaceIds('u', [], 'secret_text_s'));
        $this->assertSame([], $as->getHiddenScopes('u', [], 'secret_text_s'));
        $this->assertEquals(new Query\Terms('workspaceId', [$w2->getId()]), $as->createVisibilityQuery('u', [], 'secret_text_s'));

        $titleScope = ['w' => $w1->getId(), 'p' => [$parent->getAbsolutePath(), $child->getAbsolutePath()]];
        $this->assertNull($as->getReadableWorkspaceIds('u', [], 'title_text_s'));
        $this->assertSame([$titleScope], $as->getHiddenScopes('u', [], 'title_text_s'));
        $this->assertSame([
            'bool' => [
                'must_not' => [
                    ['bool' => ['filter' => [
                        ['term' => ['workspaceId' => $w1->getId()]],
                        ['term' => ['referenceCollectionPath' => $parent->getAbsolutePath()]],
                        ['term' => ['referenceCollectionPath' => $child->getAbsolutePath()]],
                    ]]],
                ],
            ],
        ], $as->createVisibilityQuery('u', [], 'title_text_s')?->toArray());

        $this->assertNull($as->getReadableWorkspaceIds('u', [], 'notes_text_s'));
        $this->assertSame([], $as->getHiddenScopes('u', [], 'notes_text_s'));
        $this->assertNull($as->createVisibilityQuery('u', [], 'notes_text_s'));

        $clusters = [];
        foreach ($as->buildSearchableAttributeDefinitionsGroups('u', []) as $cluster) {
            foreach (array_keys($cluster['fields']) as $field) {
                $clusters[$field] = ['w' => $cluster['w'], 'x' => $cluster['x'] ?? []];
            }
        }
        $bothWorkspaces = [$w1->getId(), $w2->getId()];
        sort($bothWorkspaces);
        $this->assertEquals([
            AttributeInterface::ATTRIBUTES_FIELD.'._.secret_text_s' => ['w' => [$w2->getId()], 'x' => []],
            AttributeInterface::ATTRIBUTES_FIELD.'._.title_text_s' => ['w' => $bothWorkspaces, 'x' => [$titleScope]],
            AttributeInterface::ATTRIBUTES_FIELD.'._.city_text_s' => ['w' => [$w1->getId()], 'x' => [$titleScope]],
            AttributeInterface::ATTRIBUTES_FIELD.'._.notes_text_s' => ['w' => null, 'x' => []],
            'name' => ['w' => null, 'x' => []],
        ], $clusters);
    }

    private function createAttributeSearch(array $rows = [], array $policies = [], array $collections = []): AttributeSearch
    {
        $attributeTypeRegistry = AttributeTypeRegistryTestFactory::create();
        $builtInAttributeRegistry = new BuiltInAttributeRegistry(new ServiceLocator([]));

        $repository = $this->createStub(AttributeDefinitionRepository::class);
        $repository->method('getAttributesWithPermission')->willReturn($rows);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $policyRepository = $this->createStub(AssetPolicyRepository::class);
        $policyRepository->method('getAssetPolicies')->willReturn($policies);
        $collectionsById = [];
        foreach ($collections as $collection) {
            $collectionsById[$collection->getId()] = $collection;
        }
        $collectionRepository = $this->createStub(CollectionRepository::class);
        $collectionRepository->method('find')->willReturnCallback(static fn (string $id): ?Collection => $collectionsById[$id] ?? null);

        return new AttributeSearch(
            new FieldNameResolver($attributeTypeRegistry, $builtInAttributeRegistry),
            $em,
            $attributeTypeRegistry,
            new AQLParser(),
            new AQLToESQuery(new AQLConditionResolver(
                $builtInAttributeRegistry,
                new AQLFunctionRegistry(),
                $attributeTypeRegistry,
                new DateNormalizer(),
            )),
            new AssetPolicyManager($policyRepository, $collectionRepository, new TemporaryCacheFactory()),
            new TemporaryCacheFactory(),
        );
    }

    /**
     * @param string[] $hiddenDefinitionIds
     */
    private static function createPolicy(Workspace $workspace, array $hiddenDefinitionIds, array $conditions = []): AssetPolicy
    {
        $policy = new AssetPolicy();
        $policy->setWorkspace($workspace);
        $policy->setConditions($conditions);
        $policy->setActions(array_map(static fn (string $id): array => [
            'action' => AssetPolicyManager::ACTION_HIDE_ATTRIBUTE,
            'definitionId' => $id,
        ], $hiddenDefinitionIds));

        return $policy;
    }

    private static function createRow(bool $allowed, string $wsId, string $slug, bool $searchable = true, ?string $id = null): array
    {
        return [
            'id' => $id ?? $wsId.'-'.$slug,
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
