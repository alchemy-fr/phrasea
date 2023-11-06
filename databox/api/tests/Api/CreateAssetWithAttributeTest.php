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
    public function testAssetCreateWithAttributes(array $attributes, array $expectedValues): void
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
                'title' => 'Batch attribute Asset',
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ]),
                'attributes' => $attrs,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $attrAssertions = [];
        foreach ($expectedValues as $name => $value) {
            $attrAssertions[] = [
                'definition' => [
                    'name' => $name,
                ],
                'value' => $value,
            ];
        }
        $this->assertJsonContains([
            '@type' => 'asset',
            'title' => 'Batch attribute Asset',
            'attributes' => $attrAssertions,
        ]);
        $this->assertMatchesRegularExpression('~^/assets/'.AlchemyApiTestCase::UUID_REGEX.'$~', $response->toArray()['@id']);
        $this->assertMatchesResourceItemJsonSchema(Asset::class);
    }

    public function getCases(): array
    {
        return [
            [['Description' => 'Foo bar', 'Keywords' => 'KW #1'], ['Description' => 'Foo bar', 'Keywords' => ['KW #1']]],
            [['Description' => 'Foo bar', 'Keywords' => ['KW #1']], ['Description' => 'Foo bar', 'Keywords' => ['KW #1']]],
            [['Description' => 'Foo bar', 'Keywords' => ['KW #1', 'KW #2']], ['Description' => 'Foo bar', 'Keywords' => ['KW #1', 'KW #2']]],
        ];
    }
}
