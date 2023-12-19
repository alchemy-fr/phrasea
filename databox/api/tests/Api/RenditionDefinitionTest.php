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
}
