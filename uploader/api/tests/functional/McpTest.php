<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\McpBundle\Test\McpClientTrait;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Api\Processor\DownloadUrlProcessor;
use App\Consumer\Handler\Download;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\Target;
use App\Tests\Functional\Databox\FakeDataboxClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class McpTest extends AbstractUploaderTestCase
{
    use McpClientTrait;

    /**
     * Queues the messages of the "p2" transport (the downloads) instead of handling them, when set.
     *
     * @var list<object>|null
     */
    private ?array $queuedMessages = null;

    public function testUnauthenticatedRequestPointsToResourceMetadata(): void
    {
        $response = $this->mcpRequest(null, 'POST', '/mcp', content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ], JSON_THROW_ON_ERROR));

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString(
            'resource_metadata="http://localhost/.well-known/oauth-protected-resource"',
            (string) $response->headers->get('WWW-Authenticate'),
        );

        $response = $this->mcpRequest(null, 'GET', '/.well-known/oauth-protected-resource');
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('http://localhost/mcp', $json['resource']);
        $this->assertCount(1, $json['authorization_servers']);
        $this->assertEquals('Phrasea Uploader', $json['resource_name']);

        // The variant suffixed with the resource path, tried first by MCP clients
        $response = $this->mcpRequest(null, 'GET', '/.well-known/oauth-protected-resource/mcp');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('http://localhost/mcp', json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['resource']);
    }

    public function testListTools(): void
    {
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $result = $this->mcpRpc(self::token(KeycloakClientTestMock::USER_UID), 'tools/list');
        $names = array_column($result['tools'], 'name');
        sort($names);

        $this->assertSame([
            'acknowledge_commit',
            'acknowledge_upload_asset',
            'create_target',
            'create_upload_link',
            'delete_target',
            'delete_upload_link',
            'get_commit',
            'get_target',
            'get_target_data',
            'get_target_form_schema',
            'get_target_settings',
            'get_upload_asset',
            'get_upload_link',
            'list_commits',
            'list_form_field_options',
            'list_targets',
            'list_upload_links',
            'regenerate_upload_link_token',
            'update_target',
            'update_target_data',
            'update_target_form',
            'update_upload_link',
            'upload_from_url',
            'validate_form_data',
        ], $names);
    }

    public function testListAndGetTargets(): void
    {
        $production = $this->createTarget('Databox production');
        $this->createTarget('Databox staging');
        $this->createTarget('Archive');
        $this->createTarget('Restricted', ['some-group']);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'list_targets');
        $this->assertEqualsCanonicalizing(
            ['Databox production', 'Databox staging', 'Archive'],
            array_column($result['items'], 'name'),
        );

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'list_targets', ['query' => 'databox']);
        $this->assertEqualsCanonicalizing(['Databox production', 'Databox staging'], array_column($result['items'], 'name'));

        $target = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'get_target', ['id' => $production->getId()]);
        $this->assertEquals('Databox production', $target['name']);
    }

    public function testTargetOfAnotherGroupIsDenied(): void
    {
        $target = $this->createTarget('Restricted', ['some-group']);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'get_target_form_schema', [
            'id' => $target->getId(),
        ], expectError: true);
        $this->assertStringContainsString('Uploader API responded 403', $error);
    }

    public function testFormSchemaAndValidation(): void
    {
        $target = $this->createTarget('With form');
        $target->setForm([
            'version' => 1,
            'workspaceId' => null,
            'fields' => [
                ['id' => 'f_album', 'source' => 'custom', 'key' => 'album', 'type' => 'text', 'required' => true, 'label' => ['_' => 'Album']],
            ],
        ]);
        $em = self::getEntityManager();
        $em->persist($target);
        $em->flush();
        $withoutForm = $this->createTarget('Without form');
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'get_target_form_schema', ['id' => $target->getId()]);
        $this->assertEquals($target->getId(), $result['targetId']);
        $this->assertEquals('album', $result['form']['fields'][0]['key']);

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'get_target_form_schema', ['id' => $withoutForm->getId()]);
        $this->assertNull($result['form']);

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'validate_form_data', [
            'targetId' => $target->getId(),
            'data' => ['album' => 'Foo'],
        ]);
        $this->assertEquals(['valid' => true, 'errors' => []], $result);

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'validate_form_data', [
            'targetId' => $target->getId(),
            'data' => ['album' => ''],
        ]);
        $this->assertFalse($result['valid']);
        $this->assertEquals(['album' => ['This value should not be blank.']], $result['errors']);
    }

    public function testCommitsRequireTheScopeOrTheAdminRole(): void
    {
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'list_commits', expectError: true);
        $this->assertStringContainsString('Uploader API responded 403', $error);
    }

    public function testListAndGetCommits(): void
    {
        $target = $this->createTarget('Target');
        $other = $this->createTarget('Other');
        [$commitId, $assetId] = $this->createCommit($target);
        $this->createCommit($other);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_commits');
        $this->assertEquals(2, $result['totalItems']);

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_commits', [
            'targetId' => $target->getId(),
            'acknowledged' => false,
        ]);
        $this->assertEquals([$commitId], array_column($result['items'], 'id'));

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_commits', ['acknowledged' => true]);
        $this->assertSame([], $result['items']);

        $commit = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'get_commit', ['id' => $commitId]);
        $this->assertEquals(['album' => 'Foo'], $commit['formData']);
        $this->assertEquals($assetId, $commit['assets'][0]['id']);

        $asset = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'get_upload_asset', ['id' => $assetId]);
        $this->assertEquals('foo.jpeg', $asset['originalName']);
    }

    public function testUploadAssetRequiresTheAdminRole(): void
    {
        [, $assetId] = $this->createCommit($this->createTarget('Target'));
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'get_upload_asset', ['id' => $assetId], expectError: true);
        $this->assertStringContainsString('Uploader API responded 403', $error);
    }

    public function testUploadFromUrlQueuesTheDownload(): void
    {
        $target = $this->createTarget('Target');
        $target->setForm([
            'version' => 1,
            'workspaceId' => null,
            'fields' => [
                ['id' => 'f_album', 'source' => 'custom', 'key' => 'album', 'type' => 'text', 'required' => true, 'label' => ['_' => 'Album']],
            ],
        ]);
        $em = self::getEntityManager();
        $em->persist($target);
        $em->flush();
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getService('limiter.'.DownloadUrlProcessor::RATE_LIMITER);
        // Its state outlives the test
        $limiter->create(KeycloakClientTestMock::USER_UID)->reset();
        $this->queuedMessages = [];
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'upload_from_url', [
            'url' => 'https://example.com/a.jpg',
            'targetId' => $target->getId(),
            'formData' => ['album' => ''],
        ]);
        $this->assertEquals(['errors' => ['album' => ['This value should not be blank.']]], $result);
        $this->assertSame([], $this->queuedDownloads());

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'upload_from_url', [
            'url' => 'https://example.com/a.jpg',
            'targetId' => $target->getId(),
            'formData' => ['album' => 'Foo'],
        ]);
        $this->assertSame(['result' => true], $result);
        $downloads = $this->queuedDownloads();
        $this->assertCount(1, $downloads);
        $this->assertSame('https://example.com/a.jpg', $downloads[0]->url);
        $this->assertSame($target->getId(), $downloads[0]->targetId);
        $this->assertSame(KeycloakClientTestMock::USER_UID, $downloads[0]->userId);
        $this->assertSame(['album' => 'Foo'], $downloads[0]->formData);

        $restricted = $this->createTarget('Restricted', ['some-group']);
        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'upload_from_url', [
            'url' => 'https://example.com/a.jpg',
            'targetId' => $restricted->getId(),
        ], expectError: true);
        $this->assertStringContainsString('Uploader API responded 403', $error);
        $this->assertCount(1, $this->queuedDownloads());
    }

    public function testFormFieldOptions(): void
    {
        $entityId = 'a1b2c3d4-e5f6-4789-8abc-def012345678';
        $target = $this->createTarget('With entities');
        $target->setForm([
            'version' => 1,
            'workspaceId' => '7e5ab1c2-0a7e-4b8a-9d2c-5c1b2f0e6a01',
            'fields' => [
                [
                    'id' => 'keywords',
                    'source' => 'attribute',
                    'key' => 'keywords',
                    'type' => 'entity',
                    'multiple' => true,
                    'attribute' => ['id' => 'y', 'slug' => 'keywords', 'databoxType' => 'entity', 'entityListId' => '2b3c4d5e-6f70-4182-93a4-b5c6d7e8f901'],
                ],
                ['id' => 'title', 'source' => 'custom', 'key' => 'title', 'type' => 'text'],
            ],
        ]);
        $em = self::getEntityManager();
        $em->persist($target);
        $em->flush();
        $this->databox = new FakeDataboxClient([
            '/attribute-entities' => ['member' => [['id' => $entityId, 'value' => 'Sea']]],
        ]);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'list_form_field_options', [
            'targetId' => $target->getId(),
            'fieldKey' => 'keywords',
            'query' => 'se',
        ]);
        $this->assertEquals([
            'items' => [['value' => $entityId, 'label' => 'Sea', 'color' => null, 'emoji' => null]],
            'page' => 1,
            'hasNextPage' => false,
        ], $result);
        $this->assertSame('se', $this->databox->calls[0]['query']['query']);

        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'list_form_field_options', [
            'targetId' => $target->getId(),
            'fieldKey' => 'title',
        ], expectError: true);
        $this->assertStringContainsString('Uploader API responded 404', $error);
    }

    public function testUploadLinks(): void
    {
        $target = $this->createTarget('Target');
        $admin = self::token(KeycloakClientTestMock::ADMIN_UID);
        $this->mcpInitialize($admin);

        $link = $this->mcpCallTool($admin, 'create_upload_link', [
            'targetId' => $target->getId(),
            'expiresAt' => new \DateTimeImmutable('+7 days')->format(\DateTimeInterface::ATOM),
            'name' => 'Photographers',
            'maxAssets' => 10,
        ]);
        $this->assertSame('Photographers', $link['name']);
        $this->assertSame(10, $link['maxAssets']);
        $this->assertSame('active', $link['status']);
        $this->assertSame($target->getId(), $link['target']['id']);

        $result = $this->mcpCallTool($admin, 'list_upload_links', ['targetId' => $target->getId()]);
        $this->assertSame([$link['id']], array_column($result['items'], 'id'));

        $updated = $this->mcpCallTool($admin, 'update_upload_link', [
            'id' => $link['id'],
            'enabled' => false,
            'clear' => ['maxAssets'],
        ]);
        $this->assertSame('disabled', $updated['status']);
        $this->assertNull($updated['maxAssets'] ?? null);
        $this->assertSame('Photographers', $updated['name']);

        $regenerated = $this->mcpCallTool($admin, 'regenerate_upload_link_token', ['id' => $link['id']]);
        $this->assertNotSame($link['token'], $regenerated['token']);
        $this->assertSame($regenerated['token'], $this->mcpCallTool($admin, 'get_upload_link', ['id' => $link['id']])['token']);

        $this->assertSame(['deleted' => $link['id']], $this->mcpCallTool($admin, 'delete_upload_link', ['id' => $link['id']]));
        $this->assertSame([], $this->mcpCallTool($admin, 'list_upload_links')['items']);
    }

    public function testUploadLinksRequireThePermission(): void
    {
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'list_upload_links', expectError: true);
        $this->assertStringContainsString('Uploader API responded 403', $error);
    }

    public function testTargetAdministration(): void
    {
        $admin = self::token(KeycloakClientTestMock::ADMIN_UID);
        $this->mcpInitialize($admin);

        $target = $this->mcpCallTool($admin, 'create_target', [
            'name' => 'Pushed',
            'slug' => 'pushed',
            'description' => 'Where my files go',
            'targetUrl' => 'https://example.com/upload',
            'authorizationScheme' => 'ApiKey',
            'authorizationKey' => 'secret',
            'allowedGroups' => ['group-a'],
        ]);
        $this->assertSame('Pushed', $target['name']);
        $this->assertArrayNotHasKey('authorizationKey', $target);

        $updated = $this->mcpCallTool($admin, 'update_target', [
            'id' => $target['id'],
            'name' => 'Renamed',
            'clear' => ['description', 'authorizationKey'],
        ]);
        $this->assertSame('Renamed', $updated['name']);
        $this->assertNull($updated['description'] ?? null);
        $this->assertSame('https://example.com/upload', $updated['targetUrl']);

        $settings = $this->mcpCallTool($admin, 'get_target_settings', ['id' => $target['id']]);
        $this->assertSame(['group-a'], $settings['allowedGroups']);
        $this->assertFalse($settings['hasAuthorizationKey']);

        $form = [
            'version' => 1,
            'workspaceId' => null,
            'fields' => [
                ['id' => 'f_album', 'source' => 'custom', 'key' => 'album', 'type' => 'text', 'required' => true, 'label' => ['_' => 'Album']],
            ],
        ];
        $result = $this->mcpCallTool($admin, 'update_target_form', ['id' => $target['id'], 'form' => $form]);
        $this->assertEquals($form, $result['form']);
        $this->assertEquals($form, $this->mcpCallTool($admin, 'get_target_form_schema', ['id' => $target['id']])['form']);
        $error = $this->mcpCallTool($admin, 'update_target_form', [
            'id' => $target['id'],
            'form' => ['version' => 99, 'fields' => []],
        ], expectError: true);
        $this->assertStringContainsString('Uploader API responded 422', $error);
        $this->assertNull($this->mcpCallTool($admin, 'update_target_form', ['id' => $target['id'], 'form' => null])['form']);

        $this->assertNull($this->mcpCallTool($admin, 'get_target_data', ['id' => $target['id']])['data']);
        $result = $this->mcpCallTool($admin, 'update_target_data', ['id' => $target['id'], 'data' => ['origin' => 'mcp']]);
        $this->assertSame(['targetId' => $target['id'], 'data' => ['origin' => 'mcp']], $result);
        $this->assertSame(['origin' => 'mcp'], $this->mcpCallTool($admin, 'get_target_data', ['id' => $target['id']])['data']);

        $this->assertSame(['deleted' => $target['id']], $this->mcpCallTool($admin, 'delete_target', ['id' => $target['id']]));
        $error = $this->mcpCallTool($admin, 'get_target', ['id' => $target['id']], expectError: true);
        $this->assertStringContainsString('Uploader API responded 404', $error);
    }

    public function testTargetAdministrationRequiresTheAdminRole(): void
    {
        $target = $this->createTarget('Target');
        $user = self::token(KeycloakClientTestMock::USER_UID);
        $this->mcpInitialize($user);

        foreach ([
            ['create_target', ['name' => 'Forbidden']],
            ['update_target', ['id' => $target->getId(), 'name' => 'Hijacked']],
            ['delete_target', ['id' => $target->getId()]],
            ['get_target_settings', ['id' => $target->getId()]],
            ['update_target_form', ['id' => $target->getId(), 'form' => null]],
            ['get_target_data', ['id' => $target->getId()]],
        ] as [$tool, $arguments]) {
            $error = $this->mcpCallTool($user, $tool, $arguments, expectError: true);
            $this->assertStringContainsString('Uploader API responded 403', $error, $tool);
        }
    }

    public function testAcknowledge(): void
    {
        $target = $this->createTarget('Target');
        [$commitId, $assetId] = $this->createCommit($target);
        [$otherCommitId] = $this->createCommit($target);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'acknowledge_commit', ['id' => $commitId], expectError: true);
        $this->assertStringContainsString('Uploader API responded 403', $error);

        $admin = self::token(KeycloakClientTestMock::ADMIN_UID);
        $this->mcpSessionId = null;
        $this->mcpInitialize($admin);

        // The only file of the commit: the commit is acknowledged with it
        $this->assertSame(['result' => true], $this->mcpCallTool($admin, 'acknowledge_upload_asset', ['id' => $assetId]));
        $this->assertTrue($this->mcpCallTool($admin, 'get_commit', ['id' => $commitId])['acknowledged']);

        $this->assertSame(['result' => true], $this->mcpCallTool($admin, 'acknowledge_commit', ['id' => $otherCommitId]));
        $result = $this->mcpCallTool($admin, 'list_commits', ['acknowledged' => false]);
        $this->assertSame([], $result['items']);
    }

    protected function mcpRequest(?string $accessToken, string $method, string $uri, array $server = [], ?string $content = null): Response
    {
        self::getEntityManager()->clear();

        if (null !== $accessToken) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$accessToken;
        }
        $server['CONTENT_TYPE'] ??= 'application/json';
        $server['HTTP_ACCEPT'] ??= 'application/json';

        $browser = $this->createClientWithDatabox()->getKernelBrowser();
        $queue = null;
        if (null !== $this->queuedMessages) {
            /** @var TestTransport $transport */
            $transport = static::getContainer()->get('messenger.transport.p2');
            $queue = $transport->intercept();
        }

        $browser->request($method, $uri, server: $server, content: $content);

        foreach ($queue?->getSent() ?? [] as $envelope) {
            $this->queuedMessages[] = $envelope->getMessage();
        }

        return $browser->getResponse();
    }

    /**
     * @return list<Download>
     */
    private function queuedDownloads(): array
    {
        return array_values(array_filter($this->queuedMessages ?? [], static fn (object $message): bool => $message instanceof Download));
    }

    private static function token(string $userId): string
    {
        return KeycloakClientTestMock::getJwtFor($userId);
    }

    private function createTarget(string $name, ?array $allowedGroups = null): Target
    {
        $target = new Target();
        $target->setName($name);
        $target->setSlug(strtolower(str_replace(' ', '-', $name)));
        $target->setTargetUrl('http://localhost');
        $target->setAllowedGroups($allowedGroups);

        $em = self::getEntityManager();
        $em->persist($target);
        $em->flush();

        return $target;
    }

    /**
     * @return array{string, string} the IDs of the commit and of its asset
     */
    private function createCommit(Target $target): array
    {
        $commit = new Commit();
        $commit->setTarget($target);
        $commit->setFormData(['album' => 'Foo']);
        $asset = $this->createAsset($target);
        $asset->setCommit($commit);

        $commit->getAssets()->add($asset);
        $commit->setTotalSize(42);
        $commit->setUserId('user_id');
        $commit->setToken('secret_token');

        $em = self::getEntityManager();
        $em->persist($commit);
        $em->flush();

        return [$commit->getId(), $asset->getId()];
    }

    /**
     * An uploaded file, not committed yet: persisted, not flushed.
     */
    private function createAsset(Target $target): Asset
    {
        $asset = new Asset();
        $asset->setTarget($target);
        $asset->setMimeType('image/jpeg');
        $asset->setPath('a/b/c.jpeg');
        $asset->setSize(42);
        $asset->setOriginalName('foo.jpeg');
        $asset->setUserId('user_id');
        self::getEntityManager()->persist($asset);

        return $asset;
    }
}
