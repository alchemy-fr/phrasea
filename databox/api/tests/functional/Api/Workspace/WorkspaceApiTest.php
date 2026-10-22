<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Workspace;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\Similarity\SimilarityIntegration;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Read side of /workspaces: visibility rules (public, owner, user/group ACL,
 * anonymous, admin), serialization groups (list vs item), computed fields
 * (capabilities, displayName, termsUnsigned) and /workspaces-by-slug.
 */
final class WorkspaceApiTest extends AbstractDataboxTestCase
{
    use WorkspaceTestHelperTrait;

    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    /**
     * Creates one workspace per visibility case, all owned by someone else than USER
     * (except "owned").
     */
    private function createVisibilityMatrix(): void
    {
        $this->createWs('private-ws');
        $this->createWs('public-ws', ['public' => true]);
        $this->createWs('owned-ws', ['ownerId' => self::USER]);
        $this->grantUser(self::USER, $this->createWs('user-acl-ws'));
        $this->grantGroup(self::GROUP_ID, $this->createWs('group-acl-ws'));
        // An ACE without the VIEW bit does not give visibility
        $this->grantUser(self::USER, $this->createWs('child-acl-ws'), PermissionInterface::CHILD_VIEW);
        // ACE given to another user
        $this->grantUser(self::OTHER, $this->createWs('other-acl-ws'));
    }

    public function testAnonymousOnlyListsPublicWorkspaces(): void
    {
        $this->createVisibilityMatrix();

        $response = static::createClient()->request('GET', '/workspaces');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(['public-ws'], self::listedSlugs($data));
        $this->assertSame(1, $data['totalItems']);
    }

    public function testUserListsPublicOwnedAndGrantedWorkspaces(): void
    {
        $this->createVisibilityMatrix();

        $response = static::createClient()->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::USER),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(['owned-ws', 'public-ws', 'user-acl-ws'], self::listedSlugs($response->toArray()));
    }

    public function testGroupAclGivesVisibilityToGroupMembers(): void
    {
        $this->createVisibilityMatrix();

        $response = static::createClient()->request('GET', '/workspaces', [
            'headers' => self::groupedUserHeaders(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(['group-acl-ws', 'public-ws'], self::listedSlugs($response->toArray()));

        // Not a member of the group
        $response = static::createClient()->request('GET', '/workspaces', [
            'headers' => self::groupedUserHeaders(['another-group']),
        ]);
        $this->assertSame(['public-ws'], self::listedSlugs($response->toArray()));
    }

    public function testWildcardAceGivesVisibilityOnAllWorkspaces(): void
    {
        $this->createVisibilityMatrix();
        // An ACE without object id applies to every workspace
        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            self::OTHER,
            'workspace',
            null,
            PermissionInterface::VIEW,
        );

        $client = static::createClient();
        $response = $client->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::OTHER),
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertCount(7, $response->toArray()['member']);

        $client->request('GET', self::iri($this->findOneBy(Workspace::class, ['slug' => 'private-ws'])), [
            'headers' => self::authHeaders(self::OTHER),
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function testWorkspaceReadableThroughOwnerAceIsListed(): void
    {
        $this->markTestIncomplete('BUG: WorkspaceExtension only joins ACEs having the VIEW bit whereas WorkspaceVoter READ_NO_TERMS accepts VIEW or OWNER: a workspace readable through an OWNER-only ACE is missing from GET /workspaces');

        $ws = $this->createWs('owner-ace-ws');
        // OWNER without the VIEW bit: the voter grants READ (VIEW or OWNER)…
        $this->grantUser(self::USER, $ws, PermissionInterface::OWNER);

        $client = static::createClient();
        $client->request('GET', self::iri($ws), [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertResponseIsSuccessful();

        // …so the list should return it as well
        $response = $client->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertSame(['owner-ace-ws'], self::listedSlugs($response->toArray()));
    }

    public function testAdminListsEveryWorkspace(): void
    {
        $this->createVisibilityMatrix();

        $response = static::createClient()->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::ADMIN),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'child-acl-ws',
            'group-acl-ws',
            'other-acl-ws',
            'owned-ws',
            'private-ws',
            'public-ws',
            'user-acl-ws',
        ], self::listedSlugs($response->toArray()));
    }

    public function testSoftDeletedWorkspaceIsNeitherListedNorReadable(): void
    {
        $ws = $this->createWs('public-ws', ['public' => true]);
        $id = $ws->getId();

        $em = self::getEntityManager();
        $ws->setDeletedAt(new \DateTimeImmutable());
        $em->flush();

        $client = static::createClient();
        $response = $client->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::ADMIN),
        ]);
        $this->assertSame([], self::listedSlugs($response->toArray()));

        $client->request('GET', self::iri($id), [
            'headers' => self::authHeaders(self::ADMIN),
        ]);
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', '/workspaces-by-slug/public-ws', [
            'headers' => self::authHeaders(self::ADMIN),
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testListIsSearchedByNameAndOrderedByName(): void
    {
        $this->createWs('zulu-ws', ['name' => 'Zulu photos', 'ownerId' => self::USER]);
        $this->createWs('alpha-ws', ['name' => 'Alpha photos', 'ownerId' => self::USER]);
        $this->createWs('mike-ws', ['name' => 'Mike Photos', 'ownerId' => self::USER]);
        $this->createWs('videos-ws', ['name' => 'Videos', 'ownerId' => self::USER]);
        $this->createWs('private-ws', ['name' => 'Private photos']);

        $listNames = static function (array $query): array {
            $response = static::createClient()->request('GET', '/workspaces', [
                'headers' => self::authHeaders(self::USER),
                'query' => $query,
            ]);
            self::assertResponseIsSuccessful();

            return array_column($response->toArray()['member'], 'name');
        };

        $this->assertSame(['Alpha photos', 'Mike Photos', 'Videos', 'Zulu photos'], $listNames([]));
        $this->assertSame(['Alpha photos', 'Mike Photos', 'Zulu photos'], $listNames(['name' => 'PHOTO']));
    }

    /**
     * "query" matches the name or any translation of it (whatever its locale, never the locale
     * itself), within the visible workspaces; "name" stays a match on the name only.
     */
    public function testListIsSearchedByTranslatedNames(): void
    {
        $this->createWs('photos-ws', [
            'name' => 'Photos',
            'ownerId' => self::USER,
            'translations' => ['name' => ['fr' => 'Clichés', 'de' => 'Aufnahmen']],
        ]);
        $this->createWs('emptied-ws', [
            'name' => 'Emptied',
            'ownerId' => self::USER,
            'translations' => ['name' => ['fr' => '']],
        ]);
        $this->createWs('videos-ws', ['name' => 'Videos', 'ownerId' => self::USER]);
        $this->createWs('private-ws', [
            'name' => 'Private',
            'translations' => ['name' => ['fr' => 'Clichés privés']],
        ]);

        $listNames = static function (array $query): array {
            $response = static::createClient()->request('GET', '/workspaces', [
                'headers' => self::authHeaders(self::USER),
                'query' => $query,
            ]);
            self::assertResponseIsSuccessful();

            return array_column($response->toArray()['member'], 'name');
        };

        foreach (['clichés', 'Clichés', 'ichés', 'AUFNAHME', 'phot'] as $query) {
            $this->assertSame(['Photos'], $listNames(['query' => $query]), $query);
        }
        $this->assertSame([], $listNames(['query' => 'fr']));
        $this->assertSame(['Emptied'], $listNames(['query' => 'empt']));
        $this->assertSame(['Videos'], $listNames(['query' => 'video']));
        $this->assertSame([], $listNames(['name' => 'clichés']));
    }

    public function testListIsResolvedByIds(): void
    {
        $owned = $this->createWs('owned-ws', ['name' => 'Owned', 'ownerId' => self::USER]);
        $public = $this->createWs('public-ws', ['name' => 'Public', 'public' => true]);
        $private = $this->createWs('private-ws', ['name' => 'Private']);
        $deleted = $this->createWs('deleted-ws', ['name' => 'Deleted', 'ownerId' => self::USER]);
        $deleted->setDeletedAt(new \DateTimeImmutable());
        self::getEntityManager()->flush();
        $this->createWs('other-ws', ['name' => 'Other', 'ownerId' => self::USER]);

        $response = static::createClient()->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::USER),
            'query' => ['ids' => [$public->getId(), $private->getId(), $deleted->getId(), $owned->getId()]],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame([self::iri($owned), self::iri($public)], array_column($data['member'], '@id'));
        $this->assertSame(2, $data['totalItems']);

        static::createClient()->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::USER),
            'query' => ['ids' => ['not-a-uuid']],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public static function getItemAccessProvider(): array
    {
        return [
            // workspace slug, user (null = anonymous), expected status
            'anonymous / private' => ['private-ws', null, 401],
            'anonymous / public' => ['public-ws', null, 200],
            'user / private' => ['private-ws', self::USER, 403],
            'user / public' => ['public-ws', self::USER, 200],
            'user / owned' => ['owned-ws', self::USER, 200],
            'user / user ACL' => ['user-acl-ws', self::USER, 200],
            'user / ACL without VIEW' => ['child-acl-ws', self::USER, 403],
            'user / ACL of another user' => ['other-acl-ws', self::USER, 403],
            'other / ACL' => ['other-acl-ws', self::OTHER, 200],
            'admin / private' => ['private-ws', self::ADMIN, 200],
            'admin / ACL of another user' => ['other-acl-ws', self::ADMIN, 200],
        ];
    }

    #[DataProvider('getItemAccessProvider')]
    public function testGetItemAccess(string $slug, ?string $userId, int $expectedStatus): void
    {
        $this->createVisibilityMatrix();
        $ws = $this->findOneBy(Workspace::class, ['slug' => $slug]);

        $response = static::createClient()->request('GET', self::iri($ws), [
            'headers' => self::authHeaders($userId),
        ]);

        $this->assertResponseStatusCodeSame($expectedStatus);
        if (200 === $expectedStatus) {
            $this->assertSame($slug, $response->toArray()['slug']);
        }
    }

    #[DataProvider('getItemAccessProvider')]
    public function testGetBySlugAccess(string $slug, ?string $userId, int $expectedStatus): void
    {
        $this->createVisibilityMatrix();
        $ws = $this->findOneBy(Workspace::class, ['slug' => $slug]);

        $response = static::createClient()->request('GET', '/workspaces-by-slug/'.$slug, [
            'headers' => self::authHeaders($userId),
        ]);

        $this->assertResponseStatusCodeSame($expectedStatus);
        if (200 === $expectedStatus) {
            $data = $response->toArray();
            $this->assertSame($ws->getId(), $data['id']);
            $this->assertSame($slug, $data['slug']);
        }
    }

    public function testGroupMemberCanReadGroupWorkspace(): void
    {
        $this->createVisibilityMatrix();
        $ws = $this->findOneBy(Workspace::class, ['slug' => 'group-acl-ws']);

        $client = static::createClient();
        $client->request('GET', self::iri($ws), [
            'headers' => self::groupedUserHeaders(),
        ]);
        $this->assertResponseIsSuccessful();

        $client->request('GET', self::iri($ws), [
            'headers' => self::groupedUserHeaders(['another-group']),
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetUnknownWorkspace(): void
    {
        $client = static::createClient();
        $client->request('GET', '/workspaces/2f1c7f0e-0b0a-4b6e-9a51-3c1b4f8f0a99', [
            'headers' => self::authHeaders(self::ADMIN),
        ]);
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', '/workspaces-by-slug/does-not-exist', [
            'headers' => self::authHeaders(self::ADMIN),
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testItemExposesReadGroupWhereasListDoesNot(): void
    {
        $ws = $this->createWs('detailed-ws', [
            'ownerId' => self::USER,
            'enabledLocales' => ['fr', 'en'],
        ]);
        $ws->setLocaleFallbacks(['fr']);
        $ws->setTrashRetentionDelay(12);
        self::getEntityManager()->flush();

        $client = static::createClient();
        $response = $client->request('GET', self::iri($ws), [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertResponseIsSuccessful();
        $item = $response->toArray();
        $this->assertSame('workspace', $item['@type']);
        $this->assertSame('detailed-ws', $item['slug']);
        $this->assertSame('Workspace detailed-ws', $item['name']);
        $this->assertSame(['fr', 'en'], $item['enabledLocales']);
        $this->assertSame(['fr'], $item['localeFallbacks']);
        $this->assertSame(12, $item['trashRetentionDelay']);
        $this->assertSame(self::USER, $item['ownerId']);
        $this->assertFalse($item['public']);
        $this->assertFalse($item['fileAnalysisRequired']);
        $this->assertArrayHasKey('owner', $item);
        $this->assertArrayHasKey('capabilities', $item);
        $this->assertFalse($item['termsUnsigned']);

        $response = $client->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::USER),
        ]);
        $listed = $response->toArray()['member'][0];
        $this->assertSame('detailed-ws', $listed['slug']);
        $this->assertSame(['fr', 'en'], $listed['enabledLocales']);
        $this->assertArrayHasKey('capabilities', $listed);
        $this->assertArrayHasKey('ownerId', $listed);
        foreach (['localeFallbacks', 'trashRetentionDelay', 'assetDefaultStatus', 'fileAnalysisRequired', 'translations', 'owner', 'terms'] as $readOnlyField) {
            $this->assertArrayNotHasKey($readOnlyField, $listed, sprintf('"%s" must only be exposed on the item', $readOnlyField));
        }
    }

    public static function capabilitiesProvider(): array
    {
        $all = ['createAsset' => true, 'createCollection' => true, 'edit' => true, 'delete' => true, 'editPermissions' => true];
        $none = ['createAsset' => false, 'createCollection' => false, 'edit' => false, 'delete' => false, 'editPermissions' => false];

        return [
            'owner' => ['owned', self::USER, $all],
            'admin' => ['private', self::ADMIN, $all],
            'VIEW ACE' => ['view', self::USER, $none],
            'public workspace' => ['public', self::USER, $none],
            'anonymous on public workspace' => ['public', null, $none],
            'EDIT ACE' => ['edit', self::USER, ['edit' => true] + $none],
            'DELETE ACE' => ['delete', self::USER, ['delete' => true] + $none],
            'CREATE ACE' => ['create', self::USER, ['createCollection' => true] + $none],
            'CHILD_CREATE ACE' => ['child-create', self::USER, ['createAsset' => true] + $none],
            'OWNER ACE' => ['owner-ace', self::USER, [
                'createAsset' => true,
                'createCollection' => true,
                'edit' => true,
                'delete' => false,
                'editPermissions' => true,
            ]],
        ];
    }

    #[DataProvider('capabilitiesProvider')]
    public function testCapabilities(string $case, ?string $userId, array $expected): void
    {
        $ws = match ($case) {
            'owned' => $this->createWs('ws', ['ownerId' => self::USER]),
            'private' => $this->createWs('ws'),
            'public' => $this->createWs('ws', ['public' => true]),
            default => $this->createWs('ws'),
        };
        $mask = match ($case) {
            'view' => PermissionInterface::VIEW,
            'edit' => PermissionInterface::VIEW | PermissionInterface::EDIT,
            'delete' => PermissionInterface::VIEW | PermissionInterface::DELETE,
            'create' => PermissionInterface::VIEW | PermissionInterface::CREATE,
            'child-create' => PermissionInterface::VIEW | PermissionInterface::CHILD_CREATE,
            'owner-ace' => PermissionInterface::VIEW | PermissionInterface::OWNER,
            default => null,
        };
        if (null !== $mask) {
            $this->grantUser(self::USER, $ws, $mask);
        }

        $client = static::createClient();
        $response = $client->request('GET', self::iri($ws), [
            'headers' => self::authHeaders($userId),
        ]);
        $this->assertResponseIsSuccessful();
        $capabilities = $response->toArray()['capabilities'];
        ksort($capabilities);
        ksort($expected);
        $this->assertSame($expected, $capabilities);

        // Same capabilities in the list
        $response = $client->request('GET', '/workspaces', [
            'headers' => self::authHeaders($userId),
        ]);
        $capabilities = $response->toArray()['member'][0]['capabilities'];
        ksort($capabilities);
        $this->assertSame($expected, $capabilities);
    }

    public function testSimilarityTellsWhetherTheSimilarityIntegrationIsEnabled(): void
    {
        $enabled = $this->createWs('similarity-ws', ['public' => true]);
        $disabled = $this->createWs('disabled-similarity-ws', ['public' => true]);
        $this->createWs('no-similarity-ws', ['public' => true]);
        $em = self::getEntityManager();
        foreach ([[$enabled, true], [$disabled, false]] as [$workspace, $isEnabled]) {
            $integration = new WorkspaceIntegration();
            $integration->setWorkspace($workspace);
            $integration->setIntegration(SimilarityIntegration::getName());
            $integration->setOwnerId('custom_owner');
            $integration->setPublic(false);
            $integration->setConfig([]);
            $integration->setEnabled($isEnabled);
            $em->persist($integration);
        }
        $em->flush();

        $client = static::createClient();
        $response = $client->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::USER),
        ]);
        $similarity = array_column($response->toArray()['member'], 'similarity', 'slug');
        ksort($similarity);
        $this->assertSame([
            'disabled-similarity-ws' => false,
            'no-similarity-ws' => false,
            'similarity-ws' => true,
        ], $similarity);

        $response = $client->request('GET', self::iri($enabled), [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertTrue($response->toArray()['similarity']);
    }

    public function testDisplayNameIsTranslatedAccordingToUserLocale(): void
    {
        $this->createWs('translated-ws', [
            'name' => 'My workspace',
            'public' => true,
            'translations' => [
                'name' => [
                    'fr' => 'Mon espace',
                    'de' => '',
                ],
            ],
        ]);

        $client = static::createClient();
        $response = $client->request('GET', '/workspaces-by-slug/translated-ws', [
            'headers' => ['X-Data-Locale' => 'fr'],
        ]);
        $data = $response->toArray();
        $this->assertSame('My workspace', $data['name']);
        $this->assertSame('Mon espace', $data['displayName']);
        // Empty translations are dropped
        $this->assertSame(['name' => ['fr' => 'Mon espace']], $data['translations']);

        $response = $client->request('GET', '/workspaces-by-slug/translated-ws', [
            'headers' => ['Accept-Language' => 'fr-FR,fr;q=0.9'],
        ]);
        $this->assertSame('Mon espace', $response->toArray()['displayName']);

        // No translation for the requested locale: fallback on the name
        $response = $client->request('GET', '/workspaces-by-slug/translated-ws', [
            'headers' => ['X-Data-Locale' => 'de', 'Accept-Language' => 'de'],
        ]);
        $this->assertSame('My workspace', $response->toArray()['displayName']);
    }

    public function testBySlugDoesNotRequireSignedTerms(): void
    {
        $ws = $this->createWs('ws-with-terms');
        $this->grantUser(self::USER, $ws);

        $client = static::createClient();
        $client->request('PATCH', self::iri($ws), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + self::authHeaders(self::ADMIN),
            'json' => ['terms' => 'Accept me.'],
        ]);
        $this->assertResponseIsSuccessful();

        // Both endpoints only require READ_NO_TERMS so that the user can see the terms to sign
        foreach ([self::iri($ws), '/workspaces-by-slug/ws-with-terms'] as $uri) {
            $response = $client->request('GET', $uri, [
                'headers' => self::authHeaders(self::USER),
            ]);
            $this->assertResponseIsSuccessful();
            $this->assertTrue($response->toArray()['termsUnsigned']);
        }

        // The list flags the workspace as well
        $response = $client->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertTrue($response->toArray()['member'][0]['termsUnsigned']);

        // Anonymous users are never asked to sign
        $em = self::getEntityManager();
        $em->find(Workspace::class, $ws->getId())->setPublic(true);
        $em->flush();
        $response = $client->request('GET', self::iri($ws));
        $this->assertResponseIsSuccessful();
        $this->assertFalse($response->toArray()['termsUnsigned']);
    }
}
