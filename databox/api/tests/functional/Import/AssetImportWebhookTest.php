<?php

declare(strict_types=1);

namespace App\Tests\Functional\Import;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Consumer\Handler\Import\DispatchAssetImport;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\AssetImport\AssetImportIntegration;
use App\Integration\Phrasea\Uploader\UploaderIntegration;
use App\Model\AssetImportStatusEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The "Asset import" integration receives import instructions on a webhook authenticated
 * by the API key of its configuration; the import runs as the owner of the integration.
 */
final class AssetImportWebhookTest extends AbstractDataboxTestCase
{
    use AssetImportTestTrait;

    private const string KEY = 'the-security-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->interceptQueues();
    }

    public function testTheWebhookCreatesAnImportAsTheIntegrationOwner(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        $collection = $this->createCollection(['workspace' => $workspace, 'ownerId' => self::OWNER]);
        $integration = $this->createImportIntegration($workspace, [
            'collectionId' => $collection->getId(),
            'privacy' => 2,
        ]);

        $response = $this->callWebhook($integration->getId(), self::ndjson([
            ['key' => 'a', 'name' => 'A'],
            ['key' => 'b', 'name' => 'B'],
        ]));
        $this->assertResponseStatusCodeSame(202);
        $importId = $response->toArray()['id'];
        $this->assertCount(1, $this->getQueuedMessages($this->taskQueue, DispatchAssetImport::class));

        $import = $this->findImport($importId);
        $this->assertSame(self::OWNER, $import->getOwnerId());
        $this->assertSame($integration->getId(), $import->getIntegration()->getId());
        $this->assertSame($collection->getId(), $import->getCollection()->getId());
        $this->assertSame(2, $import->getPrivacy());
        $this->assertSame('ndjson', $import->getFormat());

        $this->consumeImportMessages();
        $import = $this->findImport($importId);
        $this->assertSame(AssetImportStatusEnum::Completed, $import->getStatus());
        $this->assertSame(2, $import->getCreated());

        $asset = self::getEntityManager()->getRepository(Asset::class)->findOneBy(['workspace' => $workspace->getId(), 'key' => 'a']);
        $this->assertSame(self::OWNER, $asset->getOwnerId());
        $this->assertSame($collection->getId(), $asset->getReferenceCollectionId());
        $this->assertSame(2, $asset->getPrivacy());

        $data = $this->apiRequest('GET', '/asset-imports/'.$importId, self::OWNER)->toArray();
        $this->assertSame($integration->getId(), $data['integrationId']);
    }

    public function testTheFormatIsGivenByTheContentType(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        $integration = $this->createImportIntegration($workspace);

        $importId = $this->callWebhook($integration->getId(), json_encode([['name' => 'A']]), contentType: 'application/json; charset=utf-8')->toArray()['id'];
        $this->assertResponseStatusCodeSame(202);
        $this->assertSame('json', $this->findImport($importId)->getFormat());

        $this->callWebhook($integration->getId(), '{"name": "A"}', contentType: 'application/jsonl');
        $this->assertResponseStatusCodeSame(202);

        $this->callWebhook($integration->getId(), 'name;url', contentType: 'text/csv');
        $this->assertResponseStatusCodeSame(415);

        $this->callWebhook($integration->getId(), '');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testTheWebhookIsAuthenticatedByTheKeyOfAnEnabledImportIntegration(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        $integration = $this->createImportIntegration($workspace);
        $body = self::ndjson([['name' => 'A']]);

        $this->callWebhook($integration->getId(), $body, null);
        $this->assertResponseStatusCodeSame(401);

        $this->callWebhook($integration->getId(), $body, 'Bearer '.self::KEY);
        $this->assertResponseStatusCodeSame(401);

        $this->callWebhook($integration->getId(), $body, 'ApiKey wrong-key');
        $this->assertResponseStatusCodeSame(403);

        $this->callWebhook('9b2a7f5e-0000-4000-8000-000000000000', $body);
        $this->assertResponseStatusCodeSame(404);

        $this->callWebhook('not-an-id', $body);
        $this->assertResponseStatusCodeSame(404);

        $uploader = $this->createIntegration($workspace, UploaderIntegration::NAME, [
            'baseUrl' => 'https://uploader.test',
            'securityKey' => self::KEY,
        ]);
        $this->callWebhook($uploader->getId(), $body);
        $this->assertResponseStatusCodeSame(404);

        $disabled = $this->createImportIntegration($workspace, enabled: false);
        $this->callWebhook($disabled->getId(), $body);
        $this->assertResponseStatusCodeSame(404);

        $this->assertSame([], $this->getQueuedMessages($this->taskQueue, DispatchAssetImport::class));
    }

    public function testTheSecurityKeyIsGeneratedAtCreationAndRenewedByDuplication(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);

        $data = $this->apiRequest('POST', '/integrations', self::OWNER, [
            'integration' => AssetImportIntegration::NAME,
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'Imports',
            'public' => false,
            'config' => [],
        ])->toArray();
        $this->assertResponseStatusCodeSame(201);
        $key = self::getEntityManager()->find(WorkspaceIntegration::class, $data['id'])->getConfig()['securityKey'] ?? null;
        $this->assertIsString($key);
        $this->assertSame(64, strlen($key));

        $copy = $this->apiRequest('POST', '/integrations/'.$data['id'].'/duplicate', self::OWNER, [])->toArray();
        $this->assertResponseStatusCodeSame(201);
        self::getEntityManager()->clear();
        $copyKey = self::getEntityManager()->find(WorkspaceIntegration::class, $copy['id'])->getConfig()['securityKey'] ?? null;
        $this->assertIsString($copyKey);
        $this->assertNotSame($key, $copyKey);
    }

    public function testTheDefaultCollectionMustBelongToTheWorkspace(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        [$otherWorkspace] = $this->createWorkspaceWithDefaults('ws-other', self::OWNER);
        $otherCollection = $this->createCollection(['workspace' => $otherWorkspace, 'ownerId' => self::OWNER]);

        $this->apiRequest('POST', '/integrations', self::OWNER, [
            'integration' => AssetImportIntegration::NAME,
            'workspace' => '/workspaces/'.$workspace->getId(),
            'public' => false,
            'config' => ['collectionId' => $otherCollection->getId()],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    private function callWebhook(string $integrationId, string $body, ?string $authorization = 'ApiKey '.self::KEY, string $contentType = 'application/x-ndjson'): ResponseInterface
    {
        $headers = ['Content-Type' => $contentType];
        if (null !== $authorization) {
            $headers['Authorization'] = $authorization;
        }

        return $this->apiRequest('POST', sprintf('/integrations/asset-import/%s/imports', $integrationId), null, options: [
            'headers' => $headers,
            'body' => $body,
        ]);
    }

    private function createImportIntegration(Workspace $workspace, array $config = [], bool $enabled = true): WorkspaceIntegration
    {
        $integration = $this->createIntegration($workspace, AssetImportIntegration::NAME, ['securityKey' => self::KEY] + $config);
        $integration->setEnabled($enabled);
        self::getEntityManager()->flush();

        return $integration;
    }

    private function createIntegration(Workspace $workspace, string $type, array $config): WorkspaceIntegration
    {
        $em = self::getEntityManager();
        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($em->find(Workspace::class, $workspace->getId()));
        $integration->setOwnerId(KeycloakClientTestMock::USER_UID);
        $integration->setPublic(false);
        $integration->setIntegration($type);
        $integration->setConfig($config);
        $em->persist($integration);
        $em->flush();

        return $integration;
    }
}
