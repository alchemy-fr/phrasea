<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Workspace;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\Workspace\DeleteWorkspace;
use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\Collection;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Write side of /workspaces: who may create/update/delete, input validation
 * (slug, locales, trash retention), partial updates and config fields.
 */
final class WorkspaceWriteTest extends AbstractDataboxTestCase
{
    use WorkspaceTestHelperTrait;

    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    public function testAdminCreatesWorkspace(): void
    {
        $response = static::createClient()->request('POST', '/workspaces', [
            'headers' => self::authHeaders(self::ADMIN),
            'json' => [
                'name' => 'Created by API',
                'slug' => 'created-by-api',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertMatchesUuid($data['id']);
        $this->assertSame('/workspaces/'.$data['id'], $data['@id']);
        $this->assertSame('Created by API', $data['name']);
        $this->assertSame('created-by-api', $data['slug']);
        // The authenticated user becomes the owner
        $this->assertSame(self::ADMIN, $data['ownerId']);
        // Defaults
        $this->assertFalse($data['public']);
        $this->assertSame(['en'], $data['enabledLocales']);
        $this->assertSame(['en'], $data['localeFallbacks']);
        $this->assertSame(30, $data['trashRetentionDelay']);
        $this->assertFalse($data['fileAnalysisRequired']);

        $ws = self::getEntityManager()->find(Workspace::class, $data['id']);
        $this->assertInstanceOf(Workspace::class, $ws);
        $this->assertSame(self::ADMIN, $ws->getOwnerId());
    }

    public function testAdminCanCreateWorkspaceOnBehalfOfAnotherUser(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/workspaces', [
            'headers' => self::authHeaders(self::ADMIN),
            'json' => [
                'name' => 'For user',
                'slug' => 'for-user',
                'ownerId' => self::USER,
                'public' => false,
                'enabledLocales' => ['fr', 'en_GB'],
                'localeFallbacks' => ['fr'],
                'trashRetentionDelay' => 7,
                'fileAnalysisRequired' => true,
                'assetDefaultStatus' => 1,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame(self::USER, $data['ownerId']);
        $this->assertSame(['fr', 'en_GB'], $data['enabledLocales']);
        $this->assertSame(['fr'], $data['localeFallbacks']);
        $this->assertSame(7, $data['trashRetentionDelay']);
        $this->assertTrue($data['fileAnalysisRequired']);
        $this->assertSame(1, $data['assetDefaultStatus']);

        // The new owner can see and edit it
        $response = $client->request('GET', '/workspaces', [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertSame(['for-user'], self::listedSlugs($response->toArray()));
        $this->assertTrue($response->toArray()['member'][0]['capabilities']['edit']);
    }

    public function testNonAdminCannotCreateWorkspace(): void
    {
        $client = static::createClient();
        $client->request('POST', '/workspaces', [
            'headers' => self::authHeaders(self::USER),
            'json' => [
                'name' => 'Nope',
                'slug' => 'nope',
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);

        $client->request('POST', '/workspaces', [
            'json' => [
                'name' => 'Nope',
                'slug' => 'nope',
                'ownerId' => self::USER,
            ],
        ]);
        $this->assertResponseStatusCodeSame(401);

        $this->assertNull($this->findOneBy(Workspace::class, ['slug' => 'nope']));
    }

    public static function invalidPayloadProvider(): array
    {
        return [
            'missing name' => [['slug' => 'valid-slug'], 'name'],
            'blank name' => [['name' => '', 'slug' => 'valid-slug'], 'name'],
            'missing slug' => [['name' => 'Name'], 'slug'],
            'slug too short' => [['name' => 'Name', 'slug' => 'a'], 'slug'],
            'slug too long' => [['name' => 'Name', 'slug' => str_repeat('a', 51)], 'slug'],
            'slug with uppercase' => [['name' => 'Name', 'slug' => 'My-Workspace'], 'slug'],
            'slug with spaces' => [['name' => 'Name', 'slug' => 'my workspace'], 'slug'],
            'slug starting with a dash' => [['name' => 'Name', 'slug' => '-workspace'], 'slug'],
            'slug ending with a dash' => [['name' => 'Name', 'slug' => 'workspace-'], 'slug'],
            'invalid locale' => [['name' => 'Name', 'slug' => 'valid-slug', 'enabledLocales' => ['fr', 'FR-fr']], 'enabledLocales[1]'],
            'blank locale' => [['name' => 'Name', 'slug' => 'valid-slug', 'enabledLocales' => ['']], 'enabledLocales[0]'],
            'negative trash retention' => [['name' => 'Name', 'slug' => 'valid-slug', 'trashRetentionDelay' => -1], 'trashRetentionDelay'],
            'too long trash retention' => [['name' => 'Name', 'slug' => 'valid-slug', 'trashRetentionDelay' => 731], 'trashRetentionDelay'],
        ];
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testCreateValidation(array $payload, string $propertyPath): void
    {
        $response = static::createClient()->request('POST', '/workspaces', [
            'headers' => self::authHeaders(self::ADMIN),
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(422);
        $violations = array_column($response->toArray(false)['violations'], 'propertyPath');
        $this->assertContains($propertyPath, $violations);
    }

    public function testSlugMustBeUnique(): void
    {
        $this->createWs('taken');

        $client = static::createClient();
        $response = $client->request('POST', '/workspaces', [
            'headers' => self::authHeaders(self::ADMIN),
            'json' => [
                'name' => 'Duplicate',
                'slug' => 'taken',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame([[
            'propertyPath' => 'slug',
            'message' => 'Slug is already taken',
        ]], array_map(fn (array $v): array => [
            'propertyPath' => $v['propertyPath'],
            'message' => $v['message'],
        ], $response->toArray(false)['violations']));

        // Same on update
        $other = $this->createWs('other');
        $client->request('PATCH', self::iri($other), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + self::authHeaders(self::ADMIN),
            'json' => ['slug' => 'taken'],
        ]);
        $this->assertResponseStatusCodeSame(422);

        // Keeping its own slug is fine
        $client->request('PATCH', self::iri($other), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + self::authHeaders(self::ADMIN),
            'json' => ['slug' => 'other', 'name' => 'Renamed'],
        ]);
        $this->assertResponseIsSuccessful();
    }

    public static function updateAccessProvider(): array
    {
        return [
            // user, ACE mask on the workspace (null = none), expected status
            'anonymous' => [null, null, 401],
            'user without access' => [self::USER, null, 403],
            'user with VIEW' => [self::USER, PermissionInterface::VIEW, 403],
            'user with DELETE' => [self::USER, PermissionInterface::VIEW | PermissionInterface::DELETE, 403],
            'user with EDIT' => [self::USER, PermissionInterface::VIEW | PermissionInterface::EDIT, 200],
            'user with OWNER' => [self::USER, PermissionInterface::VIEW | PermissionInterface::OWNER, 200],
            'owner' => ['owner', null, 200],
            'admin' => [self::ADMIN, null, 200],
        ];
    }

    #[DataProvider('updateAccessProvider')]
    public function testUpdateAccess(?string $userId, ?int $mask, int $expectedStatus): void
    {
        $isOwner = 'owner' === $userId;
        $ws = $this->createWs('ws', [
            'public' => true,
            'ownerId' => $isOwner ? self::OTHER : 'custom_owner',
        ]);
        if (null !== $mask) {
            $this->grantUser($userId, $ws, $mask);
        }

        $response = static::createClient()->request('PATCH', self::iri($ws), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + self::authHeaders($isOwner ? self::OTHER : $userId),
            'json' => ['name' => 'Updated'],
        ]);

        $this->assertResponseStatusCodeSame($expectedStatus);
        $expectedName = 200 === $expectedStatus ? 'Updated' : 'Workspace ws';
        if (200 === $expectedStatus) {
            $this->assertSame('Updated', $response->toArray()['name']);
        }
        // The denied payload is denormalized onto the managed entity before the security check: reload from the database
        $em = self::getEntityManager();
        $em->clear();
        $this->assertSame($expectedName, $em->find(Workspace::class, $ws->getId())->getName());
    }

    public function testUpdateIsPartialAndHandlesConfigFields(): void
    {
        $ws = $this->createWs('ws', [
            'name' => 'Original',
            'enabledLocales' => ['fr', 'en'],
        ]);

        $client = static::createClient();
        $response = $client->request('PATCH', self::iri($ws), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + self::authHeaders(self::ADMIN),
            'json' => [
                'public' => true,
                'trashRetentionDelay' => '15',
                'assetDefaultStatus' => 1,
                'fileAnalysisRequired' => true,
                'translations' => ['name' => ['fr' => 'Originel']],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        // Untouched fields are kept
        $this->assertSame('Original', $data['name']);
        $this->assertSame('ws', $data['slug']);
        $this->assertSame(['fr', 'en'], $data['enabledLocales']);
        // Updated ones
        $this->assertTrue($data['public']);
        $this->assertSame(15, $data['trashRetentionDelay']);
        $this->assertSame(1, $data['assetDefaultStatus']);
        $this->assertTrue($data['fileAnalysisRequired']);
        $this->assertSame(['name' => ['fr' => 'Originel']], $data['translations']);

        // Reverting the config to its defaults removes the keys from the stored config
        $response = $client->request('PATCH', self::iri($ws), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + self::authHeaders(self::ADMIN),
            'json' => [
                'assetDefaultStatus' => 0,
                'fileAnalysisRequired' => false,
                'enabledLocales' => ['de'],
                'localeFallbacks' => ['de', 'en'],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(0, $data['assetDefaultStatus']);
        $this->assertFalse($data['fileAnalysisRequired']);
        $this->assertSame(['de'], $data['enabledLocales']);
        $this->assertSame(['de', 'en'], $data['localeFallbacks']);

        $stored = self::getEntityManager()->find(Workspace::class, $ws->getId());
        $this->assertSame(['trashRetentionDelay' => 15], $stored->getConfig());
    }

    public function testUnknownAssetDefaultStatusFallsBackToAccepted(): void
    {
        $ws = $this->createWs('ws');

        $response = static::createClient()->request('PATCH', self::iri($ws), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + self::authHeaders(self::ADMIN),
            'json' => ['assetDefaultStatus' => 999],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $response->toArray()['assetDefaultStatus']);
    }

    public function testUpdateValidation(): void
    {
        $ws = $this->createWs('ws');

        $client = static::createClient();
        foreach ([
            ['slug' => 'Invalid Slug'],
            ['enabledLocales' => ['english']],
            ['trashRetentionDelay' => 1000],
        ] as $payload) {
            $client->request('PATCH', self::iri($ws), [
                'headers' => ['Content-Type' => 'application/merge-patch+json'] + self::authHeaders(self::ADMIN),
                'json' => $payload,
            ]);
            $this->assertResponseStatusCodeSame(422, json_encode($payload));
        }

        $em = self::getEntityManager();
        $em->clear();
        $this->assertSame('ws', $em->find(Workspace::class, $ws->getId())->getSlug());
    }

    public function testOwnershipCannotBeTransferredByUpdate(): void
    {
        $ws = $this->createWs('ws', ['ownerId' => self::USER]);

        $response = static::createClient()->request('PATCH', self::iri($ws), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + self::authHeaders(self::USER),
            'json' => ['ownerId' => self::OTHER, 'name' => 'Still mine'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(self::USER, $response->toArray()['ownerId']);
        $this->assertSame(self::USER, self::getEntityManager()->find(Workspace::class, $ws->getId())->getOwnerId());
    }

    public static function deleteAccessProvider(): array
    {
        return [
            // user, ACE mask on the workspace (null = none), expected status
            'anonymous' => [null, null, 401],
            'user without access' => [self::USER, null, 403],
            'user with VIEW' => [self::USER, PermissionInterface::VIEW, 403],
            'user with EDIT' => [self::USER, PermissionInterface::VIEW | PermissionInterface::EDIT, 403],
            'user with OWNER' => [self::USER, PermissionInterface::VIEW | PermissionInterface::OWNER, 403],
            'user with DELETE' => [self::USER, PermissionInterface::VIEW | PermissionInterface::DELETE, 204],
            'owner' => ['owner', null, 204],
            'admin' => [self::ADMIN, null, 204],
        ];
    }

    #[DataProvider('deleteAccessProvider')]
    public function testDeleteAccess(?string $userId, ?int $mask, int $expectedStatus): void
    {
        $isOwner = 'owner' === $userId;
        $ws = $this->createWs('ws', [
            'public' => true,
            'ownerId' => $isOwner ? self::OTHER : 'custom_owner',
        ]);
        $id = $ws->getId();
        if (null !== $mask) {
            $this->grantUser($userId, $ws, $mask);
        }

        static::createClient()->request('DELETE', self::iri($ws), [
            'headers' => self::authHeaders($isOwner ? self::OTHER : $userId),
        ]);

        $this->assertResponseStatusCodeSame($expectedStatus);
        $em = self::getEntityManager();
        $em->clear();
        if (204 === $expectedStatus) {
            // The DeleteWorkspace message is handled synchronously in test: the workspace is gone
            $this->assertNull($em->find(Workspace::class, $id));
        } else {
            $this->assertNotNull($em->find(Workspace::class, $id));
        }
    }

    public function testDeleteRemovesWorkspaceAndItsConfiguration(): void
    {
        $ws = $this->createWs('to-delete', ['ownerId' => self::USER]);
        $id = $ws->getId();
        $this->createCollection(['workspace' => $ws, 'name' => 'Col']);
        $this->createAsset(['workspace' => $ws]);

        $client = static::createClient();
        $client->request('DELETE', self::iri($ws), [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', self::iri($id), [
            'headers' => self::authHeaders(self::ADMIN),
        ]);
        $this->assertResponseStatusCodeSame(404);

        $em = self::getEntityManager();
        $em->clear();
        foreach ([
            Collection::class,
            Asset::class,
            RenditionDefinition::class,
            RenditionPolicy::class,
            AttributeDefinition::class,
            AttributePolicy::class,
            WorkspaceIntegration::class,
        ] as $class) {
            $this->assertSame([], $em->getRepository($class)->findBy(['workspace' => $id]), $class);
        }

        // The slug is released
        $client->request('POST', '/workspaces', [
            'headers' => self::authHeaders(self::ADMIN),
            'json' => ['name' => 'Again', 'slug' => 'to-delete'],
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testDeleteIsDeferredToTheDeleteWorkspaceMessage(): void
    {
        $ws = $this->createWs('soft-deleted', ['ownerId' => self::USER]);
        $id = $ws->getId();

        $client = static::createClient();
        $client->getKernelBrowser()->disableReboot();
        /** @var TestTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.p2');
        $inMemory = $transport->intercept();

        $client->request('DELETE', self::iri($ws), [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertResponseStatusCodeSame(204);

        $messages = array_values(array_filter(
            $inMemory->getSent(),
            fn ($e): bool => $e->getMessage() instanceof DeleteWorkspace,
        ));
        $this->assertCount(1, $messages);
        $this->assertSame($id, $messages[0]->getMessage()->getWorkspaceId());

        // Until the message is consumed, the workspace is only soft-deleted
        $em = self::getEntityManager();
        $em->clear();
        $stored = $em->find(Workspace::class, $id);
        $this->assertNotNull($stored);
        $this->assertNotNull($stored->getDeletedAt());

        // Its slug is still reserved meanwhile
        $client->request('POST', '/workspaces', [
            'headers' => self::authHeaders(self::ADMIN),
            'json' => ['name' => 'Again', 'slug' => 'soft-deleted'],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }
}
