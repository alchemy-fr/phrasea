<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\Rendition\BuildDynamicRendition;
use App\Entity\Core\Workspace;
use App\Tests\AbstractSearchTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class DynamicRenditionTest extends AbstractSearchTestCase
{
    private const string BUILD_DEFINITION = <<<'YAML'
image:
    transformations:
        -
            module: imagine
            options:
                filters:
                    crop:
                        start: [10, 10]
                        size: [300, 200]
                    grayscale: ~
YAML;

    public function testCreateDynamicRendition(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $client->disableReboot();
        $assetId = $this->createAssetWithSource($client);

        // Intercept the queue so that the build is not performed synchronously
        $transport = $this->interceptQueue('p2');

        $response = $client->request('POST', '/renditions', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'assetId' => $assetId,
                'name' => 'my-custom-crop',
                'buildDefinition' => self::BUILD_DEFINITION,
                'writeMetadata' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'rendition',
            'name' => 'my-custom-crop',
            'displayName' => 'my-custom-crop',
            'ready' => false,
        ]);
        $renditionId = $response->toArray()['id'];

        $buildMessages = array_filter(
            $transport->getSent(),
            fn ($envelope): bool => $envelope->getMessage() instanceof BuildDynamicRendition
        );
        $this->assertCount(1, $buildMessages);

        // The dynamic rendition must be listed along the definition-based ones
        $response = $client->request('GET', '/renditions', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'query' => [
                'assetId' => $assetId,
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $names = array_column($response->toArray()['member'], 'name');
        $this->assertContains('my-custom-crop', $names);

        // Re-creating with the same name is rejected: the rendition must be deleted first
        $client->request('POST', '/renditions', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'assetId' => $assetId,
                'name' => 'my-custom-crop',
                'buildDefinition' => self::BUILD_DEFINITION,
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);

        $client->request('DELETE', '/renditions/'.$renditionId, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testCreateDynamicRenditionWithInvalidSpec(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $assetId = $this->createAssetWithSource($client);

        $client->request('POST', '/renditions', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'assetId' => $assetId,
                'name' => 'my-custom-crop',
                'buildDefinition' => "image:\n    transformations:\n        -\n            module: unknown_module\n",
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateDynamicRenditionWithoutName(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $assetId = $this->createAssetWithSource($client);

        $client->request('POST', '/renditions', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'assetId' => $assetId,
                'buildDefinition' => self::BUILD_DEFINITION,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateDynamicRenditionIsDeniedToNonEditor(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $assetId = $this->createAssetWithSource($client);

        $client->request('POST', '/renditions', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'assetId' => $assetId,
                'name' => 'my-custom-crop',
                'buildDefinition' => self::BUILD_DEFINITION,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    private function interceptQueue(string $queueName): InMemoryTransport
    {
        /** @var TestTransport $testTransport */
        $testTransport = self::getContainer()->get('messenger.transport.'.$queueName);

        return $testTransport->intercept();
    }

    private function createAssetWithSource($client): string
    {
        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'name' => 'Dynamic rendition asset',
                'workspace' => $this->findIriBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ]),
                'sourceFile' => [
                    'url' => 'https://foo/dummy.jpg',
                    'originalName' => 'dummy.jpg',
                    'type' => 'image/jpeg',
                    'isPrivate' => false,
                    'importFile' => false,
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        return $response->toArray()['id'];
    }
}
