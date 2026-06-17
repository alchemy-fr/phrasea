<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\ApiTest\ApiTestCase as AlchemyApiTestCase;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Attribute\Type\DateTimeAttributeType;
use App\Entity\Core\Asset;
use App\Tests\AbstractSearchTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AssetAttributeBatchUpdateTest extends AbstractSearchTestCase
{
    private static array $defaultAttributes = [
        'Description' => 'This is a description test.',
        'Keywords' => ['This is KW #1', 'This is KW #2', 'This is KW #3'],
    ];

    public function testAssetAttributesBatchUpdateWithInvalidValue(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $assetIri = $this->findIriBy(Asset::class, [
            'key' => 'foo',
        ]);

        $action = function (array $actions) use ($client, $assetIri): ResponseInterface {
            return $client->request('POST', $assetIri.'/attributes', [
                'headers' => [
                    'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
                ],
                'json' => [
                    'actions' => $actions,
                ],
            ]);
        };

        $action([
            [
                'name' => 'Keywords',
                'value' => 'Foo',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);

        $action([
            [
                'name' => 'Date',
                'value' => 'invalid_date',
            ],
        ]);

        $asset = self::getEntityManager()->getRepository(Asset::class)->findOneBy([
            'key' => 'foo',
        ]);
        assert($asset instanceof Asset);

        $definition = $this->createAttributeDefinition([
            'workspace' => $asset->getWorkspace(),
            'name' => 'DateInvalid',
            'slug' => 'date_invalid',
            'type' => DateTimeAttributeType::getName(),
            'allow_invalid' => true,
        ]);

        $action([
            [
                'name' => 'keywords',
                'value' => ['Foo'],
            ],
            [
                'name' => $definition->getSlug(),
                'value' => 'invalid_date',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testAssetAttributesBatchUpdateWithNoAction(): void
    {
        $this->assetBatchAction([]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testAssetAttributesBatchUpdateWithInvalidAttributeName(): void
    {
        $this->assetBatchAction([
            [
                'name' => 'Undefined',
                'value' => 'Foo',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testAssetAttributesBatchUpdateWithInvalidAttributeId(): void
    {
        $this->assetBatchAction([
            [
                'id' => '123',
                'value' => 'Foo',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assetBatchAction([
            [
                'id' => '9881c5a7-586d-4563-9dc1-333ff6ed84b3',
                'value' => 'Foo',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * @dataProvider getCases
     */
    public function testAssetAttributesBatchUpdateOK(array $actions, array $expectedValues): void
    {
        $response = $this->assetBatchAction($actions);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $attrAssertions = [];

        ksort($expectedValues);
        foreach ($expectedValues as $name => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }
            foreach ($value as $v) {
                $attrAssertions[] = [
                    'definition' => [
                        'name' => $name,
                    ],
                    'value' => $v,
                ];
            }
        }
        $this->assertJsonContains([
            '@type' => 'asset',
            'attributes' => $attrAssertions,
        ]);
        $this->assertMatchesRegularExpression('~^/assets/'.AlchemyApiTestCase::UUID_REGEX.'$~', $response->toArray()['@id']);
    }

    private function assetBatchAction(array $actions): ResponseInterface
    {
        self::enableFixtures();

        $assetIri = $this->findIriBy(Asset::class, [
            'key' => 'foo',
        ]);

        return static::createClient()->request('POST', $assetIri.'/attributes', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'actions' => $actions,
            ],
        ]);
    }

    public function getCases(): array
    {
        $withoutKeywords = $withoutDesc = self::$defaultAttributes;
        unset($withoutDesc['Description']);
        unset($withoutKeywords['Keywords']);

        $replacedDesc = self::$defaultAttributes;
        $replacedDesc['Description'] = 'This is a replaced test.';

        $replacedAll = self::$defaultAttributes;
        $repl = fn (string $str): string => str_replace(' is', ' IS', $str);
        $replacedAll['Description'] = $repl($replacedAll['Description']);
        $replacedAll['Keywords'] = array_map($repl, $replacedAll['Keywords']);

        //        $regexDesc = self::$defaultAttributes;
        //        $regexDesc['Description'] = 'This is a de!scription te!st.';

        return [
            [
                [
                    [
                        'name' => 'description',
                        'value' => 'Foo bar',
                    ],
                    [
                        'name' => 'keywords',
                        'value' => ['This is KW #1'],
                    ],
                ], array_merge(self::$defaultAttributes, ['Description' => 'Foo bar', 'Keywords' => ['This is KW #1']]),
            ],
            [
                [
                    [
                        'name' => 'description',
                        'action' => 'delete',
                    ],
                ], $withoutDesc,
            ],
            [
                [
                    [
                        'name' => 'keywords',
                        'action' => 'delete',
                    ],
                ], $withoutKeywords,
            ],
            [
                [
                    [
                        'name' => 'description',
                        'action' => 'replace',
                        'value' => 'description',
                        'replaceWith' => 'replaced',
                    ],
                ], $replacedDesc,
            ],
            [
                [
                    [
                        'action' => 'replace',
                        'value' => ' is',
                        'replaceWith' => ' IS',
                    ],
                ], $replacedAll,
            ],
            // regex test cannot be done with SQLite
            //            [
            //                [
            //                    [
            //                        'name' => 'description',
            //                        'action' => 'replace',
            //                        'regex' => true,
            //                        'value' => '(e|#)',
            //                        'replaceWith' => '$1-',
            //                    ],
            //                ], $regexDesc,
            //            ],
            //            [
            //                [
            //                ], self::$defaultAttributes,
            //            ],
        ];
    }
}
