<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\ApiTest\ApiTestCase as AlchemyApiTestCase;
use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Tests\FixturesTrait;
use App\Tests\Search\SearchTestTrait;
use Symfony\Component\HttpKernel\KernelInterface;

class CreateAssetWithAttributeTest extends ApiTestCase
{
    use FixturesTrait;
    use SearchTestTrait;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }
        static::fixturesBootKernel($options);
        self::bootSearch(static::$kernel);

        return static::$kernel;
    }

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
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
            ],
            'json' => [
                'title' => 'Batch attribute Asset',
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ]),
                'attributes' => $attrs,
            ]
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
