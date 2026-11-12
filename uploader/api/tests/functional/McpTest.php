<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\McpBundle\Test\McpClientTrait;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\Target;
use Symfony\Component\HttpFoundation\Response;

class McpTest extends AbstractUploaderTestCase
{
    use McpClientTrait;

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
            'get_commit',
            'get_target',
            'get_target_form_schema',
            'get_upload_asset',
            'list_commits',
            'list_targets',
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

    protected function mcpRequest(?string $accessToken, string $method, string $uri, array $server = [], ?string $content = null): Response
    {
        self::getEntityManager()->clear();

        if (null !== $accessToken) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$accessToken;
        }
        $server['CONTENT_TYPE'] ??= 'application/json';
        $server['HTTP_ACCEPT'] ??= 'application/json';

        $browser = static::createClient()->getKernelBrowser();
        $browser->request($method, $uri, server: $server, content: $content);

        return $browser->getResponse();
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
        $asset = new Asset();
        $asset->setTarget($target);
        $asset->setMimeType('image/jpeg');
        $asset->setCommit($commit);
        $asset->setPath('a/b/c.jpeg');
        $asset->setSize(42);
        $asset->setOriginalName('foo.jpeg');
        $asset->setUserId('user_id');

        $commit->getAssets()->add($asset);
        $commit->setTotalSize(42);
        $commit->setUserId('user_id');
        $commit->setToken('secret_token');

        $em = self::getEntityManager();
        $em->persist($asset);
        $em->persist($commit);
        $em->flush();

        return [$commit->getId(), $asset->getId()];
    }
}
