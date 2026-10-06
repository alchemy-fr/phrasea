<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Integrations (/integrations): who sees and manages them, what they expose.
 *
 * Complements WorkspaceIntegrationTest (instance-wide integrations creation,
 * integration types items).
 */
final class IntegrationApiTest extends AbstractDataboxTestCase
{
    use IntegrationTestTrait;

    public function testAWorkspaceOwnerCreatesAnIntegrationOfItsWorkspace(): void
    {
        $workspace = $this->createSharedWorkspace();

        $response = static::createClient()->request('POST', '/integrations', [
            'headers' => $this->headers(self::OWNER),
            'json' => [
                'integration' => 'remove.bg',
                'name' => 'Background remover',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'public' => false,
                'config' => ['apiKey' => 'my-remove-bg-key'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('remove.bg', $data['integration']);
        $this->assertSame('Remove BG', $data['integrationName']);
        $this->assertSame('Background remover', $data['name']);
        $this->assertSame($workspace->getId(), $data['workspace']['id']);
        $this->assertTrue($data['enabled']);
        $this->assertFalse($data['public']);
        // Defaults of the configuration tree are not stored, the given ones are
        $this->assertStringContainsString('apiKey: my-remove-bg-key', $data['configYaml']);

        $integration = $this->findIntegration($data['id']);
        $this->assertSame(self::OWNER, $integration->getOwnerId());
        $this->assertSame($workspace->getId(), $integration->getWorkspaceId());
    }

    public function testIntegrationTypesAreAddressedWithDashes(): void
    {
        $workspace = $this->createSharedWorkspace();

        $response = static::createClient()->request('POST', '/integrations', [
            'headers' => $this->headers(self::OWNER),
            'json' => [
                'integration' => 'remove--bg',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'public' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('remove.bg', $response->toArray()['integration']);
    }

    public function testAWorkspaceMemberCannotCreateAnIntegration(): void
    {
        $workspace = $this->createSharedWorkspace();

        static::createClient()->request('POST', '/integrations', [
            'headers' => $this->headers(self::MEMBER),
            'json' => [
                'integration' => 'remove.bg',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'public' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(0, self::getEntityManager()->getRepository(WorkspaceIntegration::class)->count(['integration' => 'remove.bg']));
    }

    public static function getInvalidIntegrations(): iterable
    {
        yield 'unknown integration type' => [['integration' => 'acme.unknown', 'public' => true], 422, 'config'];
        yield 'missing required option' => [['integration' => 'core.webhook', 'public' => false, 'config' => []], 422, 'config'];
        yield 'missing integration type' => [['public' => true], 422, 'integration'];
        yield 'missing public flag' => [['integration' => 'remove.bg'], 422, 'public'];
    }

    /**
     * @dataProvider getInvalidIntegrations
     */
    public function testInvalidIntegrationsAreRejected(array $payload, int $expectedCode, string $path): void
    {
        $workspace = $this->createSharedWorkspace();

        $response = static::createClient()->request('POST', '/integrations', [
            'headers' => $this->headers(self::OWNER),
            'json' => [
                'workspace' => '/workspaces/'.$workspace->getId(),
                ...$payload,
            ],
        ]);

        $this->assertResponseStatusCodeSame($expectedCode);
        $this->assertContains($path, array_column($response->toArray(false)['violations'], 'propertyPath'));
    }

    public function testAnInvalidYamlConfigurationIsRejected(): void
    {
        $workspace = $this->createSharedWorkspace();

        $response = static::createClient()->request('POST', '/integrations', [
            'headers' => $this->headers(self::OWNER),
            'json' => [
                'integration' => 'remove.bg',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'public' => true,
                'configYaml' => "apiKey: [unclosed\n",
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('Invalid YAML configuration', $response->toArray(false)['description']);
    }

    public function testTheConfigurationIsOnlyShownToWhoCanEditTheIntegration(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'remove.bg', ['apiKey' => 'super-secret-key'], public: true);
        $id = $integration->getId();

        $response = static::createClient()->request('GET', '/integrations/'.$id, [
            'headers' => $this->headers(self::OWNER),
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertStringContainsString('super-secret-key', $data['configYaml']);
        $this->assertSame(['use' => true, 'interact' => true], $data['capabilities']);

        $response = static::createClient()->request('GET', '/integrations/'.$id, [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayNotHasKey('configYaml', $data);
        $this->assertArrayNotHasKey('lastErrors', $data);
        // Only the client configuration is exposed (none for remove.bg)
        $this->assertSame([], $data['config']);
        $this->assertStringNotContainsString('super-secret-key', $response->getContent());
        // A public integration can be used, but interacting requires CHILD_EDIT
        $this->assertSame(['use' => true, 'interact' => false], $data['capabilities']);
    }

    public function testThePrivateIntegrationsOfAWorkspaceAreListedToItsEditorsOnly(): void
    {
        $workspace = $this->createSharedWorkspace();
        $public = $this->createIntegration($workspace, 'remove.bg', public: true, name: 'Public');
        $private = $this->createIntegration($workspace, 'remove.bg', public: false, name: 'Private');
        $granted = $this->createIntegration($workspace, 'remove.bg', public: false, name: 'Granted');
        $this->grantUserOnObject(self::MEMBER, $granted, PermissionInterface::VIEW);
        $iri = '/workspaces/'.$workspace->getId();

        $mine = [$public->getId(), $private->getId(), $granted->getId()];

        $this->assertEqualsCanonicalizing(
            $mine,
            array_intersect($this->listIntegrationIds(self::OWNER, ['workspace' => $iri]), $mine),
        );
        $this->assertEqualsCanonicalizing(
            [$public->getId(), $granted->getId()],
            array_intersect($this->listIntegrationIds(self::MEMBER, ['workspace' => $iri]), $mine),
        );
        // The core integrations set up with the workspace follow the same rule:
        // the private "read metadata" one is hidden from the member
        $ownerTypes = $this->listIntegrationTypes(self::OWNER, ['workspace' => $iri]);
        $memberTypes = $this->listIntegrationTypes(self::MEMBER, ['workspace' => $iri]);
        $this->assertContains('core.read_metadata', $ownerTypes);
        $this->assertNotContains('core.read_metadata', $memberTypes);
        $this->assertContains('core.rendition', $memberTypes);
    }

    public function testIntegrationsOfAnInaccessibleWorkspaceAreNotListed(): void
    {
        $visible = $this->createSharedWorkspace();
        $hidden = $this->createOtherWorkspace();
        $visibleIntegration = $this->createIntegration($visible, 'remove.bg', public: true);
        $hiddenIntegration = $this->createIntegration($hidden, 'remove.bg', public: true);
        $global = $this->createIntegration(null, 'phrasea.expose', self::EXPOSE_CONFIG, public: true, ownerId: KeycloakClientTestMock::ADMIN_UID);
        $privateGlobal = $this->createIntegration(null, 'phrasea.expose', self::EXPOSE_CONFIG, public: false, ownerId: KeycloakClientTestMock::ADMIN_UID);

        $ids = $this->listIntegrationIds(self::MEMBER);
        $this->assertContains($visibleIntegration->getId(), $ids);
        $this->assertContains($global->getId(), $ids);
        $this->assertNotContains($hiddenIntegration->getId(), $ids);
        $this->assertNotContains($privateGlobal->getId(), $ids);

        static::createClient()->request('GET', '/integrations', [
            'headers' => $this->headers(self::MEMBER),
            'query' => ['workspace' => '/workspaces/'.$hidden->getId()],
        ]);
        $this->assertResponseStatusCodeSame(403);

        // Admins list everything of any workspace…
        $ids = $this->listIntegrationIds(KeycloakClientTestMock::ADMIN_UID, ['workspace' => '/workspaces/'.$hidden->getId()]);
        $this->assertContains($hiddenIntegration->getId(), $ids);
        // …and all the instance-wide ones
        $ids = $this->listIntegrationIds(KeycloakClientTestMock::ADMIN_UID, ['global' => 1]);
        $this->assertEqualsCanonicalizing([$global->getId(), $privateGlobal->getId()], $ids);
        // Without filter, the listing is limited to the workspaces they belong to, even for admins
        $this->assertNotContains($hiddenIntegration->getId(), $this->listIntegrationIds(KeycloakClientTestMock::ADMIN_UID));
    }

    public function testAnonymousListsNothing(): void
    {
        $this->createIntegration(null, 'phrasea.expose', self::EXPOSE_CONFIG, public: true, ownerId: KeycloakClientTestMock::ADMIN_UID);

        $response = static::createClient()->request('GET', '/integrations');

        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $response->toArray()['totalItems']);
    }

    public function testIntegrationsAreFilteredByContextAndState(): void
    {
        $workspace = $this->createSharedWorkspace();
        $expose = $this->createIntegration($workspace, 'phrasea.expose', self::EXPOSE_CONFIG, public: true);
        $tui = $this->createIntegration($workspace, 'tui.photo-editor', public: true);
        $disabled = $this->createIntegration($workspace, 'tui.photo-editor', public: true, enabled: false);
        $iri = '/workspaces/'.$workspace->getId();

        $this->assertSame([$expose->getId()], $this->listIntegrationIds(self::OWNER, ['workspace' => $iri, 'context' => 'basket']));
        $this->assertEqualsCanonicalizing(
            [$tui->getId(), $disabled->getId()],
            $this->listIntegrationIds(self::OWNER, ['workspace' => $iri, 'context' => 'asset-view']),
        );
        $this->assertSame([$tui->getId()], $this->listIntegrationIds(self::OWNER, ['workspace' => $iri, 'context' => 'asset-view', 'enabled' => 1]));
        $this->assertSame([$disabled->getId()], $this->listIntegrationIds(self::OWNER, ['workspace' => $iri, 'enabled' => 0]));

        $response = static::createClient()->request('GET', '/integrations', [
            'headers' => $this->headers(self::OWNER),
            'query' => ['context' => 'nowhere'],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Invalid context "nowhere"', $response->toArray(false)['description']);
    }

    public function testAnIntegrationOfAnotherWorkspaceCannotBeRead(): void
    {
        $hidden = $this->createOtherWorkspace();
        $integration = $this->createIntegration($hidden, 'remove.bg', ['apiKey' => 'k'], public: false, ownerId: 'someone-else');

        static::createClient()->request('GET', '/integrations/'.$integration->getId(), [
            'headers' => $this->headers(self::MEMBER),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testEditorsUpdateTheIntegration(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'remove.bg', ['apiKey' => 'k1'], public: false);
        $id = $integration->getId();

        $response = static::createClient()->request('PATCH', '/integrations/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->headers(self::OWNER),
            'json' => [
                'integration' => 'remove.bg',
                'name' => 'Renamed',
                'enabled' => false,
                'configYaml' => "apiKey: k2\nprocessIncoming: true\n",
                'if' => 'asset.getSource() != null',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('Renamed', $data['name']);
        $this->assertFalse($data['enabled']);
        $this->assertSame('asset.getSource() != null', $data['if']);

        $integration = $this->findIntegration($id);
        $this->assertSame('Renamed', $integration->getName());
        $this->assertFalse($integration->isEnabled());
        $this->assertSame('k2', $integration->getConfig()['apiKey']);
        $this->assertTrue($integration->getConfig()['processIncoming']);
        // The workspace cannot be changed
        $this->assertSame($workspace->getId(), $integration->getWorkspaceId());
    }

    public function testMembersCannotUpdateNorDeleteTheIntegration(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'remove.bg', public: true);
        $id = $integration->getId();

        static::createClient()->request('PATCH', '/integrations/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->headers(self::MEMBER),
            'json' => ['integration' => 'remove.bg', 'name' => 'Hacked'],
        ]);
        $this->assertResponseStatusCodeSame(403);

        static::createClient()->request('DELETE', '/integrations/'.$id, [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseStatusCodeSame(403);

        $this->assertNotSame('Hacked', $this->findIntegration($id)?->getName());
    }

    public function testIntegrationAclDelegateEditionAndDeletion(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'remove.bg', public: true);
        $this->grantUserOnObject(self::MEMBER, $integration, PermissionInterface::EDIT | PermissionInterface::DELETE);
        $id = $integration->getId();

        static::createClient()->request('PATCH', '/integrations/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->headers(self::MEMBER),
            'json' => ['integration' => 'remove.bg', 'name' => 'Delegated'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Delegated', $this->findIntegration($id)->getName());

        static::createClient()->request('DELETE', '/integrations/'.$id, [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->findIntegration($id));
    }

    public function testNeedsCannotBeCircular(): void
    {
        $workspace = $this->createSharedWorkspace();
        $a = $this->createIntegration($workspace, 'remove.bg', public: true, name: 'A');
        $b = $this->createIntegration($workspace, 'remove.bg', public: true, name: 'B');
        $aIri = '/integrations/'.$a->getId();
        $bIri = '/integrations/'.$b->getId();

        $response = static::createClient()->request('PATCH', $aIri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->headers(self::OWNER),
            'json' => ['integration' => 'remove.bg', 'needs' => [$bIri]],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([$bIri], $response->toArray()['needs']);

        $response = static::createClient()->request('PATCH', $bIri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->headers(self::OWNER),
            'json' => ['integration' => 'remove.bg', 'needs' => [$aIri]],
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('Circular Needs detected', $response->toArray(false)['violations'][0]['message']);

        $response = static::createClient()->request('PATCH', $bIri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->headers(self::OWNER),
            'json' => ['integration' => 'remove.bg', 'needs' => [$bIri]],
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('Cannot reference itself', $response->toArray(false)['violations'][0]['message']);
    }

    public function testDeletingAnIntegrationDeletesItsDataAndTokens(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'remove.bg', public: true);
        $data = $this->createIntegrationData($integration, self::OWNER);
        $token = $this->createIntegrationToken($integration, self::OWNER);
        $id = $integration->getId();
        // Start from a fresh entity manager: the integration created above
        // does not know its data and tokens
        self::getEntityManager()->clear();

        static::createClient()->request('DELETE', '/integrations/'.$id, [
            'headers' => $this->headers(self::OWNER),
        ]);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->findIntegration($id));
        $em = self::getEntityManager();
        $this->assertNull($em->find(IntegrationToken::class, $token->getId()));
        $this->assertNull($em->find(IntegrationData::class, $data->getId()));
    }

    public function testInstanceWideIntegrationsAreManagedByAdminsOnly(): void
    {
        $global = $this->createIntegration(null, 'phrasea.expose', self::EXPOSE_CONFIG, public: true, ownerId: KeycloakClientTestMock::ADMIN_UID);
        $id = $global->getId();

        $response = static::createClient()->request('GET', '/integrations/'.$id, [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayNotHasKey('configYaml', $data);
        $this->assertSame([], $data['config']);
        $this->assertStringNotContainsString('api-expose.phrasea.test', $response->getContent());

        static::createClient()->request('PATCH', '/integrations/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->headers(self::MEMBER),
            'json' => ['integration' => 'phrasea.expose', 'name' => 'Mine'],
        ]);
        $this->assertResponseStatusCodeSame(403);

        static::createClient()->request('DELETE', '/integrations/'.$id, [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseStatusCodeSame(403);

        static::createClient()->request('PATCH', '/integrations/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->headers(KeycloakClientTestMock::ADMIN_UID),
            'json' => ['integration' => 'phrasea.expose', 'name' => 'Expose prod'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Expose prod', $this->findIntegration($id)->getName());
    }

    public function testTheIntegrationOnlyShowsTheValidTokensOfTheCurrentUser(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'remove.bg', public: true);
        $this->createIntegrationToken($integration, self::OWNER);
        $this->createIntegrationToken($integration, self::OWNER, expiresAt: '-1 hour');
        $this->createIntegrationToken($integration, self::MEMBER);
        $this->createIntegrationToken($integration, null);

        $response = static::createClient()->request('GET', '/integrations/'.$integration->getId(), [
            'headers' => $this->headers(self::OWNER),
        ]);

        $this->assertResponseIsSuccessful();
        $tokens = $response->toArray()['tokens'];
        $this->assertCount(2, $tokens);
        $this->assertEqualsCanonicalizing([self::OWNER, null], array_map(fn (array $t): ?string => $t['userId'] ?? null, $tokens));
        foreach ($tokens as $token) {
            $this->assertFalse($token['expired']);
        }
        // The OAuth tokens themselves never leave the server
        $this->assertStringNotContainsString(self::SECRET_ACCESS_TOKEN, $response->getContent());
        $this->assertStringNotContainsString(self::SECRET_REFRESH_TOKEN, $response->getContent());
    }

    public function testIntegrationTypesCatalog(): void
    {
        $response = static::createClient()->request('GET', '/integration-types', [
            'headers' => $this->headers(self::MEMBER),
        ]);

        $this->assertResponseIsSuccessful();
        $types = array_column($response->toArray()['member'], null, 'id');
        $this->assertArrayHasKey('phrasea--expose', $types);
        $this->assertArrayHasKey('remove--bg', $types);
        $this->assertSame('remove.bg', $types['remove--bg']['name']);
        $this->assertTrue($types['remove--bg']['requiresWorkspace']);
        $this->assertContains('workflow', $types['remove--bg']['features']);
        $this->assertContains('asset-view', $types['tui--photo-editor']['features']);
        // The configuration reference documents the options
        $this->assertStringContainsString('apiKey', $types['remove--bg']['reference']);
        foreach ($types as $id => $type) {
            $this->assertStringNotContainsString('.', $id);
            $this->assertNotEmpty($type['displayName']);
        }

        static::createClient()->request('GET', '/integration-types/acme--unknown', [
            'headers' => $this->headers(self::MEMBER),
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    private function listIntegrationIds(string $userId, array $query = []): array
    {
        $response = static::createClient()->request('GET', '/integrations', [
            'headers' => $this->headers($userId),
            'query' => $query,
        ]);
        $this->assertResponseIsSuccessful();

        return array_column($response->toArray()['member'], 'id');
    }

    private function listIntegrationTypes(string $userId, array $query = []): array
    {
        $response = static::createClient()->request('GET', '/integrations', [
            'headers' => $this->headers($userId),
            'query' => $query,
        ]);
        $this->assertResponseIsSuccessful();

        return array_column($response->toArray()['member'], 'integration');
    }

    private function findIntegration(string $id): ?WorkspaceIntegration
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(WorkspaceIntegration::class, $id);
    }
}
