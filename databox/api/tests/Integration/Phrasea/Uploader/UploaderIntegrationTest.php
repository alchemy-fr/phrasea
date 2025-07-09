<?php

namespace App\Tests\Integration\Phrasea\Uploader;

use Alchemy\TestBundle\Helper\FixturesTrait;
use Alchemy\TestBundle\Helper\TestServicesTrait;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Border\UploaderClient;
use App\Border\UploaderClientMock;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Phrasea\Uploader\UploaderIntegration;
use App\Repository\Core\AssetRepository;
use App\Tests\FileUploadTrait;
use App\Tests\Rendition\Phraseanet\PhraseanetApiClientFactoryMock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class UploaderIntegrationTest extends ApiTestCase
{
    use FixturesTrait;
    use FileUploadTrait;
    use TestServicesTrait;

    public function testUploaderCanTriggerIntegrationEndpoint(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();

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

        $em->clear();
        $assetRepo = $this->getService(AssetRepository::class);
        $asset = $assetRepo->findOneBy([
            'title' => 'test_file.txt',
        ]);
        $this->assertNotNull($asset);
        $this->assertEquals('test_file.txt', $asset->getTitle());

        /** @var UploaderClientMock $uploadClient */
        $uploadClient = $this->getService(UploaderClient::class);
        $this->assertCount(1, $uploadClient->getAcknowledgedAssets());
    }

    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }
}
