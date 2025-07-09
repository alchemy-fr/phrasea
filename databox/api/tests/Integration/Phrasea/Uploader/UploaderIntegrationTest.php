<?php

namespace App\Tests\Integration\Phrasea\Uploader;

use Alchemy\TestBundle\Helper\FixturesTrait;
use Alchemy\TestBundle\Helper\TestServicesTrait;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Phrasea\Uploader\UploaderIntegration;
use App\Tests\FileUploadTrait;
use App\Tests\Rendition\Phraseanet\PhraseanetApiClientFactoryMock;
use Doctrine\ORM\EntityManagerInterface;

class UploaderIntegrationTest extends ApiTestCase
{
    use FixturesTrait;
    use FileUploadTrait;
    use TestServicesTrait;

    public function testUploaderCanTriggerIntegrationEndpoint(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();

        $queueName = 'p2';
        $inMemoryTransport = $this->interceptMessengerEvents($queueName);
        $em = self::getService(EntityManagerInterface::class);
        /** @var PhraseanetApiClientFactoryMock $clientFactory */

        /** @var Workspace $workspace */
        $workspace = $em->getRepository(Workspace::class)->findOneBy([
            'slug' => 'test-workspace',
        ]);

        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setTitle('Uploader');
        $integration->setIntegration(UploaderIntegration::getName());
        $token = 'test-token';
        $integration->setConfig([
            'baseUrl' => 'https://foo.bar',
            'securityKey' => $token,
        ]);
        $integration->setOwnerId('custom_owner');
        $em->persist($integration);
        $em->flush();

        $payload = [
            'commit_id' => '12345',
            'token' => 'foo',
        ];
        $response = $apiClient->request('POST', sprintf('/integrations/uploader/%s/incoming-commit', $integration->getId()), [
            'headers' => [
                'Authorization' => 'ApiKey invalidtoken',
            ],
            'json' => $payload,
        ]);
        $this->assertEquals(403, $response->getStatusCode());

        $response = $apiClient->request('POST', sprintf('/integrations/uploader/%s/incoming-commit', $integration->getId()), [
            'headers' => [
                'Authorization' => 'ApiKey '.$token,
            ],
            'json' => $payload,
        ]);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
