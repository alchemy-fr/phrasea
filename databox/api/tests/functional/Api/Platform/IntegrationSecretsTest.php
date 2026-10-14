<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Test\Client;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Phrasea\Uploader\UploaderIntegration;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * The configuration info of an integration may hold secrets (e.g. the Uploader
 * security key, which allows to ingest assets): only its editors see it.
 */
class IntegrationSecretsTest extends AbstractDataboxTestCase
{
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    public function testIntegrationSecretsAreOnlyShownToEditors(): void
    {
        $client = static::createClient();
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::ADMIN]);
        $this->addUserOnWorkspace(self::USER, $workspace->getId());

        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setPublic(false);
        $integration->setName('Uploader');
        $integration->setIntegration(UploaderIntegration::getName());
        $integration->setConfig([
            'baseUrl' => 'https://uploader.test',
            'securityKey' => 'uploader-secret-key',
        ]);
        $integration->setOwnerId(self::ADMIN);
        $em->persist($integration);
        $em->flush();

        // Not a member of the workspace
        $this->get($client, '/integrations/'.$integration->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        // Member (reader)
        $this->get($client, '/integrations/'.$integration->getId(), self::USER);
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('uploader-secret-key', $client->getResponse()->getContent());

        $this->get($client, '/integrations/'.$integration->getId(), self::ADMIN);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('uploader-secret-key', $client->getResponse()->getContent());
    }

    private function get(Client $client, string $uri, ?string $userId = null): array
    {
        $options = [];
        if (null !== $userId) {
            $options['headers'] = ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId)];
        }

        $response = $client->request('GET', $uri, $options);

        return $response->getStatusCode() < 300 ? $response->toArray() : [];
    }
}
