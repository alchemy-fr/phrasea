<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\OAuthClientTestMock;
use App\Entity\Core\Asset;
use App\Tests\AbstractSearchTestCase;

class LongTextTest extends AbstractSearchTestCase
{
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
                'Authorization' => 'Bearer '.OAuthClientTestMock::getJwtFor(OAuthClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'actions' => [
                    [
                        'name' => 'description',
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
                'Authorization' => 'Bearer '.OAuthClientTestMock::getJwtFor(OAuthClientTestMock::ADMIN_UID),
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
}
