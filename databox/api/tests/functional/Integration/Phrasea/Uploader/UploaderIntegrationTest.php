<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration\Phrasea\Uploader;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\TestBundle\Helper\FixturesTrait;
use Alchemy\TestBundle\Helper\TestServicesTrait;
use ApiPlatform\Test\ApiTestCase;
use ApiPlatform\Test\Client;
use App\Border\UploaderClient;
use App\Border\UploaderClientMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Blurhash\BlurhashIntegration;
use App\Integration\Phrasea\Uploader\UploaderIntegration;
use App\Repository\Core\AssetRepository;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Tests\Functional\FileUploadTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class UploaderIntegrationTest extends ApiTestCase
{
    use FixturesTrait;
    use FileUploadTrait;
    use TestServicesTrait;

    private const string SECURITY_KEY = 'test-token';

    protected static ?bool $alwaysBootKernel = true;

    public function testUploaderCanTriggerIntegrationEndpoint(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration();

        $response = $this->postCommit($apiClient, $integration, 'invalidtoken');
        $this->assertEquals(403, $response->getStatusCode());

        $response = $this->postCommit($apiClient, $integration, self::SECURITY_KEY);
        $this->assertEquals(200, $response->getStatusCode());

        $this->getService(EntityManagerInterface::class)->clear();
        $assetRepo = $this->getService(AssetRepository::class);
        /** @var Asset $asset */
        $asset = $assetRepo->findOneBy([], ['createdAt' => 'DESC']);
        $this->assertNotNull($asset);
        $this->assertSame(KeycloakClientTestMock::ADMIN_UID, $asset->getOwnerId());

        /** @var AssetNameResolver $nameResolver */
        $nameResolver = $this->getService(AssetNameResolver::class);

        $this->assertEquals('test_file.png', $nameResolver->resolveNameAsString($asset));

        /** @var UploaderClientMock $uploadClient */
        $uploadClient = $this->getService(UploaderClient::class);
        $this->assertCount(1, $uploadClient->getAcknowledgedAssets());
    }

    public function testUploadingUserMustBeAllowedToCreateAssets(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration();

        /** @var UploaderClientMock $uploadClient */
        $uploadClient = $this->getService(UploaderClient::class);
        // Only allowed to view the test workspace
        $uploadClient->userId = KeycloakClientTestMock::OTHER_USER_UID;

        $assetRepo = $this->getService(AssetRepository::class);
        $assetCount = $assetRepo->count([]);

        $this->postCommit($apiClient, $integration, self::SECURITY_KEY);

        $this->getService(EntityManagerInterface::class)->clear();
        $this->assertSame($assetCount, $assetRepo->count([]));
        $this->assertCount(0, $uploadClient->getAcknowledgedAssets());
    }

    public function testDisabledIntegrationIsNotFound(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration(enabled: false);

        $response = $this->postCommit($apiClient, $integration, self::SECURITY_KEY);
        $this->assertEquals(404, $response->getStatusCode());

        /** @var UploaderClientMock $uploadClient */
        $uploadClient = $this->getService(UploaderClient::class);
        $this->assertCount(0, $uploadClient->getAcknowledgedAssets());
    }

    public function testOtherIntegrationIsNotFound(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration(type: BlurhashIntegration::NAME);

        $response = $this->postCommit($apiClient, $integration, self::SECURITY_KEY);
        $this->assertEquals(404, $response->getStatusCode());
    }

    private function createIntegration(bool $enabled = true, string $type = UploaderIntegration::NAME): WorkspaceIntegration
    {
        $em = self::getService(EntityManagerInterface::class);

        /** @var Workspace $workspace */
        $workspace = $em->getRepository(Workspace::class)->findOneBy([
            'slug' => 'test-workspace',
        ]);

        $integration = new WorkspaceIntegration();
        $integration->setPublic(true);
        $integration->setWorkspace($workspace);
        $integration->setName('Uploader');
        $integration->setIntegration($type);
        $integration->setConfig([
            'baseUrl' => 'https://foo.bar',
            'securityKey' => self::SECURITY_KEY,
        ]);
        $integration->setEnabled($enabled);
        $integration->setOwnerId('custom_owner');
        $em->persist($integration);
        $em->flush();

        return $integration;
    }

    private function postCommit(Client $apiClient, WorkspaceIntegration $integration, string $token): ResponseInterface
    {
        return $apiClient->request('POST', sprintf('/integrations/uploader/%s/incoming-commit', $integration->getId()), [
            'headers' => [
                'Authorization' => 'ApiKey '.$token,
            ],
            'json' => [
                'commit_id' => '12345',
                'token' => 'foo',
            ],
        ]);
    }

    #[\Override]
    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }
}
