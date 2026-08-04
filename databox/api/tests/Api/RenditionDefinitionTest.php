<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\RenditionDefinition;
use App\Tests\AbstractSearchTestCase;

class RenditionDefinitionTest extends AbstractSearchTestCase
{
    public function testUpdateRenditionDefinition(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(RenditionDefinition::class, ['name' => 'preview']);

        $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'useAsThumbnail' => true,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'useAsThumbnail' => true,
        ]);
    }

    public function testUpdateRenditionDefinitionMetadata(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(RenditionDefinition::class, ['name' => 'preview']);

        $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'metadata' => [
                    'IPTC:CopyrightNotice' => '© Alchemy',
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'metadata' => [
                'IPTC:CopyrightNotice' => '© Alchemy',
            ],
        ]);

        $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'metadata' => [
                    'NotANamespacedTag' => 'foo',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);

        $response = $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'metadata' => [
                    'Unknown:Tag' => 'foo',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Unknown metadata tag', $response->getContent(false));
    }

    public function testGetRenditionDefinition(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(RenditionDefinition::class, ['name' => 'preview']);

        $response = $client->request('GET', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
        ]);
        $this->assertArrayNotHasKey('useAsThumbnail', $response->toArray());

        $response = $client->request('GET', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt(),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertArrayNotHasKey('useAsThumbnail', $response->toArray());

        $client->request('GET', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt('rendition-definition:read'),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'useAsThumbnail' => false,
        ]);
    }
}
