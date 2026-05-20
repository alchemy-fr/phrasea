<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\ApiTest\ApiTestCase as AlchemyApiTestCase;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Tests\AbstractSearchTestCase;

class CreateAssetWithAttributeTest extends AbstractSearchTestCase
{
    /**
     * @dataProvider getCases
     */
    public function testAssetCreateWithAttributes(array $attributes, ?array $expectedValues): void
    {
        self::enableFixtures();

        $attrs = [];
        foreach ($attributes as $name => $value) {
            $attrs[] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        $response = static::createClient()->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'name' => 'Batch attribute Asset',
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ]),
                'attributes' => $attrs,
            ],
        ]);

        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        if (null === $expectedValues) {
            $this->assertResponseStatusCodeSame(422);
        } else {
            $this->assertResponseStatusCodeSame(201);

            $attrAssertions = [];
            foreach ($expectedValues as $name => $value) {
                if (!is_array($value)) {
                    $value = [$value];
                }
                foreach ($value as $v) {
                    $attrAssertions[] = [
                        'definition' => [
                            'slug' => $name,
                        ],
                        'value' => $v,
                    ];
                }
            }
            $this->assertJsonContains([
                '@type' => 'asset',
                'name' => 'Batch attribute Asset',
                'attributes' => $attrAssertions,
            ]);
            $this->assertMatchesRegularExpression('~^/assets/'.AlchemyApiTestCase::UUID_REGEX.'$~', $response->toArray()['@id']);
            $this->assertMatchesResourceItemJsonSchema(Asset::class);
        }
    }

    public function getCases(): array
    {
        return [
            [['description' => 'Foo bar', 'keywords' => ['KW #1']], ['description' => 'Foo bar', 'keywords' => ['KW #1']]],
            [['description' => 'Foo bar', 'keywords' => ['KW #1']], ['description' => 'Foo bar', 'keywords' => ['KW #1']]],
            [['description' => 'Foo bar', 'keywords' => ['KW #1', 'KW #2']], ['description' => 'Foo bar', 'keywords' => ['KW #1', 'KW #2']]],
            [['date' => null], []],
            [['date' => ''], null],
            [['date' => 'bar'], null],
            [['date' => '-2007-09-10T11:45:28+00:00'], null],
            [['date' => '1997/1997_01/'], null],
        ];
    }
}
