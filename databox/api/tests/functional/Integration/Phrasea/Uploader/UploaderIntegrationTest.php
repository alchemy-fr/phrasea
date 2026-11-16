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
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Blurhash\BlurhashIntegration;
use App\Integration\Phrasea\Uploader\UploaderIntegration;
use App\Model\AssetTypeEnum;
use App\Repository\Core\AssetRepository;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Workspace\WorkspaceCreator;
use App\Tests\Functional\FileUploadTrait;
use App\Tests\Functional\WithoutSecretKeyTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class UploaderIntegrationTest extends ApiTestCase
{
    use FixturesTrait;
    use FileUploadTrait;
    use TestServicesTrait;
    use WithoutSecretKeyTrait;

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

    /**
     * The API cannot decrypt a secret: a security key referring to one matches no token,
     * neither the secret nor a placeholder.
     */
    public function testASecurityKeyReferringToASecretMatchesNoToken(): void
    {
        self::enableFixtures();
        self::removeSecretKey();
        $apiClient = static::createClient();
        $integration = $this->createIntegration(securityKey: '${UPLOADER_KEY}');
        self::createWorkspaceSecret($integration->getWorkspace(), 'UPLOADER_KEY', self::SECURITY_KEY);

        foreach (['***', self::SECURITY_KEY, '${UPLOADER_KEY}'] as $token) {
            $response = $this->postCommit($apiClient, $integration, $token);
            $this->assertEquals(403, $response->getStatusCode(), $token);
        }

        /** @var UploaderClientMock $uploadClient */
        $uploadClient = $this->getService(UploaderClient::class);
        $this->assertCount(0, $uploadClient->getAcknowledgedAssets());
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

    #[DataProvider('provideRenditionSubstitution')]
    public function testRenditionSubstitution(bool $fromUploadLink, bool $substituted): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration();
        $em = $this->getService(EntityManagerInterface::class);
        $workspace = $integration->getWorkspace();

        $definition = $this->createSubstitutableDefinition($workspace);
        $target = new Asset();
        $target->setWorkspace($workspace);
        $target->setOwnerId(KeycloakClientTestMock::ADMIN_UID);
        $em->persist($target);
        $em->flush();
        $assetCount = $this->getService(AssetRepository::class)->count([]);

        /** @var UploaderClientMock $uploadClient */
        $uploadClient = $this->getService(UploaderClient::class);
        $uploadClient->assetData = [
            'data' => [
                'targetAsset' => $target->getId(),
                'targetRendition' => $definition->getId(),
            ],
            'fromUploadLink' => $fromUploadLink,
        ];

        $this->postCommit($apiClient, $integration, self::SECURITY_KEY);

        $em->clear();
        $this->assertSame($assetCount, $this->getService(AssetRepository::class)->count([]), 'No asset is created');
        $this->assertCount($substituted ? 1 : 0, $uploadClient->getAcknowledgedAssets());
        $this->assertCount($substituted ? 1 : 0, $em->getRepository(AssetRendition::class)->findBy([
            'asset' => $target->getId(),
            'definition' => $definition->getId(),
        ]));
    }

    public static function provideRenditionSubstitution(): iterable
    {
        yield 'by the uploading user' => [false, true];
        yield 'through an upload link' => [true, false];
    }

    public function testRenditionDefinitionOfAnotherWorkspaceIsNotSubstituted(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration();
        $em = $this->getService(EntityManagerInterface::class);

        $otherWorkspace = new Workspace();
        $otherWorkspace->setName('Other');
        $otherWorkspace->setSlug('other-workspace');
        $otherWorkspace->setOwnerId(KeycloakClientTestMock::ADMIN_UID);
        $otherWorkspace->setEnabledLocales(['en']);
        $this->getService(WorkspaceCreator::class)->createWorkspace($otherWorkspace);
        $foreignDefinition = $this->createSubstitutableDefinition($otherWorkspace);
        $target = new Asset();
        $target->setWorkspace($integration->getWorkspace());
        $target->setOwnerId(KeycloakClientTestMock::ADMIN_UID);
        $em->persist($target);
        $em->flush();

        /** @var UploaderClientMock $uploadClient */
        $uploadClient = $this->getService(UploaderClient::class);
        $uploadClient->assetData = [
            'data' => [
                'targetAsset' => $target->getId(),
                'targetRendition' => $foreignDefinition->getId(),
            ],
        ];

        $this->postCommit($apiClient, $integration, self::SECURITY_KEY);

        $em->clear();
        $this->assertCount(0, $uploadClient->getAcknowledgedAssets());
        $this->assertCount(0, $em->getRepository(AssetRendition::class)->findBy([
            'asset' => $target->getId(),
        ]));
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

    private function createIntegration(bool $enabled = true, string $type = UploaderIntegration::NAME, string $securityKey = self::SECURITY_KEY): WorkspaceIntegration
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
            'securityKey' => $securityKey,
        ]);
        $integration->setEnabled($enabled);
        $integration->setOwnerId('custom_owner');
        $em->persist($integration);
        $em->flush();

        return $integration;
    }

    private function createSubstitutableDefinition(Workspace $workspace): RenditionDefinition
    {
        $em = self::getService(EntityManagerInterface::class);

        $policy = new RenditionPolicy();
        $policy->setWorkspace($workspace);
        $policy->setName('Substitution');
        $policy->setPublic(true);
        $policy->setEditable(true);
        $em->persist($policy);
        $definition = new RenditionDefinition();
        $definition->setWorkspace($workspace);
        $definition->setPolicy($policy);
        $definition->setName('substitutable');
        $definition->setTarget(AssetTypeEnum::Both);
        $definition->setBuildMode(RenditionDefinition::BUILD_MODE_PICK_SOURCE);
        $definition->setSubstitutable(true);
        $em->persist($definition);

        return $definition;
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
