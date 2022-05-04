<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Core\Asset;
use App\Tests\FixturesTrait;
use App\Tests\Search\SearchTestTrait;
use Symfony\Component\HttpKernel\KernelInterface;

class LongTextTest extends ApiTestCase
{
    use FixturesTrait;
    use SearchTestTrait;

    public function testLongTextField(): void
    {
        self::enableFixtures();
        $client = static::createClient();

        $assetIri = $this->findIriBy(Asset::class, [
            'key' => 'foo',
        ]);

        $longText = str_repeat('a long text can be indexed with 😎 ', 10000);
        $searchKeyword = 'TestDiscriminator';
        $longText .= $searchKeyword;

        $client->request('POST', $assetIri.'/attributes', [
            'headers' => [
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
            ],
            'json' => [
                'actions' => [
                    [
                        'name' => 'Description',
                        'value' => $longText,
                    ],
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@type' => 'asset',
        ]);

        static::forceNewEntitiesToBeIndexed();
        static::waitForESIndex('asset');

        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
            ],
            'query' => [
                'query' => $searchKeyword,
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $data = \GuzzleHttp\json_decode($response->getContent(), true);
        $assetResult = $data['hydra:member'][0];
        $this->assertEquals($longText, $assetResult['attributes'][0]['value']);
    }

    protected static function bootKernel(array $options = []): KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }
        static::fixturesBootKernel($options);
        self::bootSearch(static::$kernel);

        return static::$kernel;
    }
}
