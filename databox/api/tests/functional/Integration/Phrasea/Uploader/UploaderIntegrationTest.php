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
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\Collection;
use App\Entity\Core\EntityList;
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

    public function testTheIntegrationNeedsItsSecurityKey(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration();
        $list = $this->createEntityList($integration->getWorkspace());

        $response = $this->getEntities($apiClient, $integration, 'invalidtoken', ['list' => $list->getId()]);
        $this->assertEquals(403, $response->getStatusCode());

        foreach ([
            $this->createIntegration(enabled: false, name: 'Disabled'),
            $this->createIntegration(type: BlurhashIntegration::NAME),
        ] as $other) {
            $response = $this->getEntities($apiClient, $other, self::SECURITY_KEY, ['list' => $list->getId()]);
            $this->assertEquals(404, $response->getStatusCode(), $other->getName());
        }

        $response = $apiClient->request('GET', '/attribute-entities?list='.$list->getId(), [
            'headers' => ['Authorization' => 'ApiKey b0a2b1e0-6bd5-4f6a-9a1f-2a3c4d5e6f70:'.self::SECURITY_KEY],
        ]);
        $this->assertEquals(404, $response->getStatusCode(), 'Unknown integration');

        $response = $apiClient->request('GET', '/attribute-entities?list='.$list->getId(), [
            'headers' => ['Authorization' => 'ApiKey '.self::SECURITY_KEY],
        ]);
        $this->assertEquals(200, $response->getStatusCode(), 'Without integration ID, the key alone is no credential');
        $this->assertSame([], $response->toArray()['member']);
    }

    /**
     * The integration reads the entity lists only: any other operation refuses its header.
     */
    public function testTheIntegrationReadsNothingElse(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration();
        $list = $this->createEntityList($integration->getWorkspace(), [['value' => 'A']]);
        $entityId = $this->getEntityIds($list)['A'];
        $headers = ['Authorization' => sprintf('ApiKey %s:%s', $integration->getId(), self::SECURITY_KEY)];

        foreach ([
            ['GET', '/workspaces'],
            ['GET', '/entity-lists'],
            ['GET', '/attribute-entities/'.$entityId],
            ['GET', '/assets'],
            ['DELETE', '/attribute-entities/'.$entityId],
        ] as [$method, $url]) {
            $response = $apiClient->request($method, $url, ['headers' => $headers]);
            $this->assertEquals(403, $response->getStatusCode(), $method.' '.$url);
        }

        $response = $apiClient->request('POST', '/attribute-entities', [
            'headers' => $headers,
            'json' => ['list' => '/entity-lists/'.$list->getId(), 'value' => 'B'],
        ]);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertCount(1, $this->getEntityIds($list));
    }

    /**
     * Its owner (a mere reader of the workspace) sees the approved values only.
     */
    public function testTheIntegrationReadsTheApprovedValuesOfAListOfItsWorkspace(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration();
        $list = $this->createEntityList($integration->getWorkspace(), [
            ['value' => 'Bravo'],
            ['value' => 'Alpha', 'translations' => ['fr' => 'Alpha FR'], 'color' => '#ff0000', 'emoji' => '🙂'],
            ['value' => 'Zulu'],
            ['value' => 'Alpha pending', 'status' => AttributeEntity::STATUS_PENDING],
            ['value' => 'Alpha rejected', 'status' => AttributeEntity::STATUS_REJECTED],
        ]);

        $response = $this->getEntities($apiClient, $integration, self::SECURITY_KEY, [
            'list' => $list->getId(),
            'order[value]' => 'asc',
        ]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray();
        $this->assertSame(['Alpha', 'Bravo', 'Zulu'], array_column($data['member'], 'value'));
        $alpha = $data['member'][0];
        $this->assertSame(['fr' => 'Alpha FR'], $alpha['translations']);
        $this->assertSame('#ff0000', $alpha['color']);
        $this->assertSame('🙂', $alpha['emoji']);

        $byValue = $this->getEntityIds($list);
        $response = $this->getEntities($apiClient, $integration, self::SECURITY_KEY, [
            'list' => $list->getId(),
            'ids' => [$byValue['Bravo'], $byValue['Alpha pending'], $byValue['Alpha rejected']],
        ]);
        $this->assertSame([$byValue['Bravo']], array_column($response->toArray()['member'], 'id'));

        $response = $this->getEntities($apiClient, $integration, self::SECURITY_KEY, [
            'list' => $list->getId(),
            'status' => AttributeEntity::STATUS_PENDING,
        ]);
        $this->assertSame([], $response->toArray()['member'], 'No pending value, even asked for');
    }

    /**
     * Nobody is behind the integration: even when its owner edits the list or created the values,
     * only the approved ones are read.
     */
    public function testTheIntegrationReadsTheApprovedValuesWhateverTheRightsOfItsOwner(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $list = null;
        foreach ([KeycloakClientTestMock::USER_UID, KeycloakClientTestMock::ADMIN_UID] as $ownerId) {
            $integration = $this->createIntegration(name: 'Of '.$ownerId, ownerId: $ownerId);
            $list ??= $this->createEntityList($integration->getWorkspace(), [
                ['value' => 'Approved'],
                ['value' => 'Pending', 'status' => AttributeEntity::STATUS_PENDING],
                ['value' => 'Rejected', 'status' => AttributeEntity::STATUS_REJECTED],
                ['value' => 'Pending of the user', 'status' => AttributeEntity::STATUS_PENDING, 'creatorId' => KeycloakClientTestMock::USER_UID],
                ['value' => 'Rejected of the admin', 'status' => AttributeEntity::STATUS_REJECTED, 'creatorId' => KeycloakClientTestMock::ADMIN_UID],
            ]);

            $response = $this->getEntities($apiClient, $integration, self::SECURITY_KEY, ['list' => $list->getId(), 'order[value]' => 'asc']);
            $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
            $this->assertSame(['Approved'], array_column($response->toArray()['member'], 'value'), $ownerId);

            $response = $this->getEntities($apiClient, $integration, self::SECURITY_KEY, ['ids' => array_values($this->getEntityIds($list))]);
            $this->assertSame(['Approved'], array_column($response->toArray()['member'], 'value'), $ownerId.', by IDs');
        }

        $integration = $this->createIntegration(name: 'Of a disabled user', ownerId: KeycloakClientTestMock::DISABLED_USER_UID);
        $response = $this->getEntities($apiClient, $integration, self::SECURITY_KEY, ['list' => $list->getId()]);
        $this->assertEquals(403, $response->getStatusCode(), 'An integration of a disabled owner');
    }

    public function testTheValuesReadByTheIntegrationArePaginated(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration();
        $list = $this->createEntityList($integration->getWorkspace(), [
            ['value' => 'A'],
            ['value' => 'B'],
            ['value' => 'C'],
        ]);

        $values = [];
        $url = '/attribute-entities?'.http_build_query(['list' => $list->getId(), 'order[value]' => 'asc', 'limit' => 2]);
        for ($i = 0; null !== $url && $i < 5; ++$i) {
            $response = $apiClient->request('GET', $url, ['headers' => [
                'Authorization' => sprintf('ApiKey %s:%s', $integration->getId(), self::SECURITY_KEY),
            ]]);
            $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
            $data = $response->toArray();
            $values[] = array_column($data['member'], 'value');
            $url = $data['view']['next'] ?? null;
        }

        $this->assertSame([['A', 'B'], ['C']], $values);
    }

    public function testTheIntegrationReadsNoListOfAnotherWorkspace(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $integration = $this->createIntegration();

        $otherWorkspace = new Workspace();
        $otherWorkspace->setName('Other');
        $otherWorkspace->setSlug('other-workspace');
        $otherWorkspace->setOwnerId(KeycloakClientTestMock::ADMIN_UID);
        $otherWorkspace->setEnabledLocales(['en']);
        $otherWorkspace->setPublic(true);
        $this->getService(WorkspaceCreator::class)->createWorkspace($otherWorkspace);
        $foreignList = $this->createEntityList($otherWorkspace, [['value' => 'Foreign']]);

        foreach ([
            ['list' => $foreignList->getId()],
            ['list' => $foreignList->getId(), 'workspace' => $otherWorkspace->getId()],
            ['workspace' => '/workspaces/'.$otherWorkspace->getId()],
            [],
        ] as $query) {
            $response = $this->getEntities($apiClient, $integration, self::SECURITY_KEY, $query);
            $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
            $this->assertNotContains('Foreign', array_column($response->toArray()['member'], 'value'), http_build_query($query));
        }

        $response = $this->getEntities($apiClient, $integration, self::SECURITY_KEY, ['list' => 'b0a2b1e0-6bd5-4f6a-9a1f-2a3c4d5e6f70']);
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * An integration of no workspace reads the lists of the workspace of its collection.
     */
    public function testTheIntegrationWithoutWorkspaceReadsTheWorkspaceOfItsCollection(): void
    {
        self::enableFixtures();
        $apiClient = static::createClient();
        $em = self::getService(EntityManagerInterface::class);
        /** @var Workspace $workspace */
        $workspace = $em->getRepository(Workspace::class)->findOneBy(['slug' => 'test-workspace']);
        $list = $this->createEntityList($workspace, [['value' => 'A']]);

        $collection = new Collection();
        $collection->setWorkspace($workspace);
        $collection->setName('Uploads');
        $collection->setOwnerId(KeycloakClientTestMock::ADMIN_UID);
        $em->persist($collection);
        $em->flush();

        $integration = $this->createIntegration(workspace: false, name: 'Without collection');
        $response = $this->getEntities($apiClient, $integration, self::SECURITY_KEY, ['list' => $list->getId()]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame([], $response->toArray()['member']);

        $integration = $this->createIntegration(workspace: false, config: ['collectionId' => $collection->getId()]);
        $response = $this->getEntities($apiClient, $integration, self::SECURITY_KEY, ['list' => $list->getId()]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(['A'], array_column($response->toArray()['member'], 'value'));
    }

    /**
     * @param list<array{value: string, status?: int, position?: int, translations?: array<string, string>, color?: string, emoji?: string, creatorId?: string}> $values
     */
    private function createEntityList(Workspace $workspace, array $values = []): EntityList
    {
        $em = self::getService(EntityManagerInterface::class);

        $list = new EntityList();
        $list->setName('list-'.uniqid('', true));
        $list->setWorkspace($workspace);
        $em->persist($list);

        foreach ($values as $value) {
            $entity = new AttributeEntity();
            $entity->setList($list);
            $entity->setValue($value['value']);
            $entity->setStatus($value['status'] ?? AttributeEntity::STATUS_APPROVED);
            $entity->setPosition($value['position'] ?? 0);
            $entity->setTranslations($value['translations'] ?? null);
            $entity->setColor($value['color'] ?? null);
            $entity->setEmoji($value['emoji'] ?? null);
            $entity->setCreatorId($value['creatorId'] ?? null);
            $em->persist($entity);
        }
        $em->flush();

        return $list;
    }

    /**
     * @return array<string, string> the IDs of the values of the list, by value
     */
    private function getEntityIds(EntityList $list): array
    {
        $ids = [];
        foreach (self::getService(EntityManagerInterface::class)->getRepository(AttributeEntity::class)->findBy(['list' => $list->getId()]) as $entity) {
            $ids[$entity->getValue()] = $entity->getId();
        }

        return $ids;
    }

    private function getEntities(Client $apiClient, WorkspaceIntegration $integration, string $key, array $query): ResponseInterface
    {
        return $apiClient->request('GET', '/attribute-entities?'.http_build_query($query), [
            'headers' => ['Authorization' => sprintf('ApiKey %s:%s', $integration->getId(), $key)],
        ]);
    }

    private function createIntegration(
        bool $enabled = true,
        string $type = UploaderIntegration::NAME,
        string $securityKey = self::SECURITY_KEY,
        bool $workspace = true,
        string $name = 'Uploader',
        array $config = [],
        string $ownerId = KeycloakClientTestMock::USER_UID,
    ): WorkspaceIntegration {
        $em = self::getService(EntityManagerInterface::class);

        /** @var Workspace $ws */
        $ws = $em->getRepository(Workspace::class)->findOneBy([
            'slug' => 'test-workspace',
        ]);

        $integration = new WorkspaceIntegration();
        $integration->setPublic(true);
        $integration->setWorkspace($workspace ? $ws : null);
        $integration->setName($name);
        $integration->setIntegration($type);
        $integration->setConfig([
            'baseUrl' => 'https://foo.bar',
            'securityKey' => $securityKey,
            ...$config,
        ]);
        $integration->setEnabled($enabled);
        $integration->setOwnerId($ownerId);
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
