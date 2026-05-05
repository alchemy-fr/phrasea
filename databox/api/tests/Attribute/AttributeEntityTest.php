<?php

namespace App\Tests\Attribute;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Attribute\AttributeInterface;
use App\Attribute\Type\EntityAttributeType;
use App\Elasticsearch\ElasticSearchClient;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Tests\Search\AbstractSearchTest;

class AttributeEntityTest extends AbstractSearchTest
{
    public function testAttributeEntityMerge(): void
    {
        $em = self::getEntityManager();

        $workspace = $this->getOrCreateDefaultWorkspace(['no_flush']);
        $em->persist($workspace);

        $list = new EntityList();
        $list->setName('list1');
        $list->setWorkspace($workspace);
        $em->persist($list);

        $entity1 = new AttributeEntity();
        $entity1->setList($list);
        $entity1->setValue('ae1');
        $entity1->setSynonyms([
            'en' => ['ae1en1', 'ae1en2'],
            'fr' => ['ae1fr1', 'ae1fr2'],
        ]);
        $em->persist($entity1);

        $entity2 = new AttributeEntity();
        $entity2->setList($list);
        $entity2->setValue('ae2');
        $entity2->setSynonyms([
            'en' => ['ae2en1', 'ae2en2'],
            'fr' => ['ae2fr1', 'ae2fr2'],
        ]);
        $em->persist($entity2);

        $definitionSingle = $this->createAttributeDefinition([
            'name' => 'Single',
            'type' => EntityAttributeType::getName(),
            'list' => $list,
            'no_flush' => true,
        ]);

        $definitionMany = $this->createAttributeDefinition([
            'name' => 'Many',
            'type' => EntityAttributeType::getName(),
            'list' => $list,
            'multiple' => true,
            'no_flush' => true,
        ]);

        $asset = $this->createAsset([
            'title' => 'Asset1',
            'attributes' => [
                [
                    'definition' => $definitionSingle,
                    'value' => $entity1->getId(),
                    'position' => 0,
                ],
                [
                    'definition' => $definitionMany,
                    'value' => $entity1->getId(),
                    'position' => 1,
                ],
                [
                    'definition' => $definitionMany,
                    'value' => $entity2->getId(),
                    'position' => 2,
                ],
            ],
        ]);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $esClient = self::getService(ElasticSearchClient::class);
        $assetIndexName = $esClient->getIndexName('asset');
        $response = $esClient->request($assetIndexName.'/_search?q=_id:'.$asset->getId());

        $this->assertEquals([
            AttributeInterface::NO_LOCALE => [
                'many_entity_m' => [
                    [
                        'id' => $entity1->getId(),
                        'value' => 'ae1',
                    ],
                    [
                        'id' => $entity2->getId(),
                        'value' => 'ae2',
                    ],
                ],
                'single_entity_s' => [
                    'id' => $entity1->getId(),
                    'value' => 'ae1',
                ],
            ],
            'en' => [
                'many_entity_m' => [
                    [
                        'id' => $entity1->getId(),
                        'synonyms' => [
                            'ae1en1',
                            'ae1en2',
                        ],
                    ],
                    [
                        'id' => $entity2->getId(),
                        'synonyms' => [
                            'ae2en1',
                            'ae2en2',
                        ],
                    ],
                ],
                'single_entity_s' => [
                    'id' => $entity1->getId(),
                    'synonyms' => [
                        'ae1en1',
                        'ae1en2',
                    ],
                ],
            ],
            'fr' => [
                'many_entity_m' => [
                    [
                        'id' => $entity1->getId(),
                        'synonyms' => [
                            'ae1fr1',
                            'ae1fr2',
                        ],
                    ],
                    [
                        'id' => $entity2->getId(),
                        'synonyms' => [
                            'ae2fr1',
                            'ae2fr2',
                        ],
                    ],
                ],
                'single_entity_s' => [
                    'id' => $entity1->getId(),
                    'synonyms' => [
                        'ae1fr1',
                        'ae1fr2',
                    ],
                ],
            ],
        ], $response->getData()['hits']['hits'][0]['_source'][AttributeInterface::ATTRIBUTES_FIELD][0]);

        $apiClient = static::createClient();

        $apiClient->request('PUT', '/attribute-entities/'.$entity1->getId(), [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'value' => 'ae1-bis',
                'synonyms' => [
                    'en' => ['ae1en1-bis', 'ae1en2-bis'],
                    'fr' => ['ae1fr1-bis', 'ae1fr2-bis'],
                ],
            ],
        ]);
        self::waitForESIndex('asset');

        $response = $esClient->request($assetIndexName.'/_search?q=_id:'.$asset->getId());

        $attrs = $response->getData()['hits']['hits'][0]['_source'][AttributeInterface::ATTRIBUTES_FIELD][0];
        dump($attrs);
        $this->assertEquals([
            AttributeInterface::NO_LOCALE => [
                'many_entity_m' => [
                    [
                        'id' => $entity1->getId(),
                        'value' => 'ae1-bis',
                    ],
                    [
                        'id' => $entity2->getId(),
                        'value' => 'ae2',
                    ],
                ],
                'single_entity_s' => [
                    'id' => $entity1->getId(),
                    'value' => 'ae1-bis',
                ],
            ],
            'en' => [
                'many_entity_m' => [
                    [
                        'id' => $entity1->getId(),
                        'synonyms' => [
                            'ae1en1-bis',
                            'ae1en2-bis',
                        ],
                    ],
                    [
                        'id' => $entity2->getId(),
                        'synonyms' => [
                            'ae2en1',
                            'ae2en2',
                        ],
                    ],
                ],
                'single_entity_s' => [
                    'id' => $entity1->getId(),
                    'synonyms' => [
                        'ae1en1-bis',
                        'ae1en2-bis',
                    ],
                ],
            ],
            'fr' => [
                'many_entity_m' => [
                    [
                        'id' => $entity1->getId(),
                        'synonyms' => [
                            'ae1fr1-bis',
                            'ae1fr2-bis',
                        ],
                    ],
                    [
                        'id' => $entity2->getId(),
                        'synonyms' => [
                            'ae2fr1',
                            'ae2fr2',
                        ],
                    ],
                ],
                'single_entity_s' => [
                    'id' => $entity1->getId(),
                    'synonyms' => [
                        'ae1fr1-bis',
                        'ae1fr2-bis',
                    ],
                ],
            ],
        ], $attrs);

        $apiClient->request('PUT', '/attribute-entities/'.$entity2->getId().'/merge', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'ids' => [
                    $entity1->getId(),
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        self::waitForESIndex('asset');
        $response = $esClient->request($assetIndexName.'/_search?q=_id:'.$asset->getId());

        $this->assertEquals([
            AttributeInterface::NO_LOCALE => [
                'many_entity_m' => [
                    [
                        'id' => $entity2->getId(),
                        'value' => 'ae2',
                    ],
                    [
                        'id' => $entity2->getId(),
                        'value' => 'ae2',
                    ],
                ],
                'single_entity_s' => [
                    'id' => $entity2->getId(),
                    'value' => 'ae2',
                ],
            ],
            'en' => [
                'many_entity_m' => [
                    [
                        'id' => $entity2->getId(),
                        'synonyms' => [
                            'ae2en1',
                            'ae2en2',
                            'ae1en1',
                            'ae1en2',
                        ],
                    ],
                    [
                        'id' => $entity2->getId(),
                        'synonyms' => [
                            'ae2en1',
                            'ae2en2',
                            'ae1en1',
                            'ae1en2',
                        ],
                    ],
                ],
                'single_entity_s' => [
                    'id' => $entity2->getId(),
                    'synonyms' => [
                        'ae2en1',
                        'ae2en2',
                        'ae1en1',
                        'ae1en2',
                    ],
                ],
            ],
            'fr' => [
                'many_entity_m' => [
                    [
                        'id' => $entity2->getId(),
                        'synonyms' => [
                            'ae2fr1',
                            'ae2fr2',
                            'ae1fr1',
                            'ae1fr2',
                        ],
                    ],
                    [
                        'id' => $entity2->getId(),
                        'synonyms' => [
                            'ae2fr1',
                            'ae2fr2',
                            'ae1fr1',
                            'ae1fr2',
                        ],
                    ],
                ],
                'single_entity_s' => [
                    'id' => $entity2->getId(),
                    'synonyms' => [
                        'ae2fr1',
                        'ae2fr2',
                        'ae1fr1',
                        'ae1fr2',
                    ],
                ],
            ],
        ], $response->getData()['hits']['hits'][0]['_source'][AttributeInterface::ATTRIBUTES_FIELD][0]);
    }
}
