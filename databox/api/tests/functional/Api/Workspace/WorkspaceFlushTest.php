<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Workspace;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * POST /workspaces/{id}/flush: empties a workspace by replacing it with a fresh
 * copy of its configuration (same slug, new id) and deleting the old one with
 * all its content.
 */
final class WorkspaceFlushTest extends AbstractDataboxTestCase
{
    use WorkspaceTestHelperTrait;

    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    public function testFlushEmptiesWorkspaceAndKeepsItsConfiguration(): void
    {
        $ws = $this->createWs('to-flush', [
            'name' => 'To flush',
            'ownerId' => self::USER,
            'enabledLocales' => ['fr', 'en'],
            'translations' => ['name' => ['fr' => 'A vider']],
        ]);
        $ws->setTrashRetentionDelay(3);
        $ws->setPublic(true);
        $oldId = $ws->getId();
        $this->createAttributeDefinition(['workspace' => $ws, 'name' => 'Description', 'slug' => 'description']);
        $collection = $this->createCollection(['workspace' => $ws, 'name' => 'Col', 'ownerId' => self::USER]);
        $this->createAsset(['workspace' => $ws, 'collectionId' => $collection->getId(), 'ownerId' => self::USER]);
        $this->grantUser(self::OTHER, $ws, PermissionInterface::VIEW | PermissionInterface::CHILD_VIEW);
        self::getEntityManager()->flush();

        $client = static::createClient();
        $response = $client->request('POST', self::iri($ws).'/flush', [
            'headers' => self::authHeaders(self::USER),
            'json' => [],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $newId = $data['id'];
        $this->assertNotSame($oldId, $newId);
        $this->assertSame('to-flush', $data['slug']);
        $this->assertSame('To flush', $data['name']);
        $this->assertSame(self::USER, $data['ownerId']);
        $this->assertTrue($data['public']);
        $this->assertSame(['fr', 'en'], $data['enabledLocales']);
        $this->assertSame(3, $data['trashRetentionDelay']);
        $this->assertSame(['name' => ['fr' => 'A vider']], $data['translations']);

        // The old workspace is gone
        $client->request('GET', self::iri($oldId), [
            'headers' => self::authHeaders(self::ADMIN),
        ]);
        $this->assertResponseStatusCodeSame(404);

        $response = $client->request('GET', '/workspaces-by-slug/to-flush', [
            'headers' => self::authHeaders(self::USER),
        ]);
        $this->assertSame($newId, $response->toArray()['id']);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(Workspace::class, $oldId));

        // Content is not copied
        $this->assertSame([], $em->getRepository(Collection::class)->findBy(['workspace' => $newId]));
        $this->assertSame([], $em->getRepository(Asset::class)->findBy(['workspace' => $newId]));
        $this->assertSame([], $em->getRepository(Collection::class)->findBy(['workspace' => $oldId]));
        $this->assertSame([], $em->getRepository(Asset::class)->findBy(['workspace' => $oldId]));

        // Configuration is copied
        $slugs = array_map(
            fn (AttributeDefinition $d): ?string => $d->getSlug(),
            $em->getRepository(AttributeDefinition::class)->findBy(['workspace' => $newId]),
        );
        sort($slugs);
        $this->assertSame(['description', 'name'], $slugs);
        $this->assertCount(3, $em->getRepository(RenditionDefinition::class)->findBy(['workspace' => $newId]));

        // ACEs are copied: OTHER still has access to the (private) new workspace
        $em->find(Workspace::class, $newId)->setPublic(false);
        $em->flush();
        $client->request('GET', self::iri($newId), [
            'headers' => self::authHeaders(self::OTHER),
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function testFlushCopiesTermsAsANewVersion(): void
    {
        $ws = $this->createWs('with-terms', ['ownerId' => self::ADMIN]);
        $this->grantUser(self::USER, $ws);

        $client = static::createClient();
        $client->request('PUT', self::iri($ws), [
            'headers' => self::authHeaders(self::ADMIN),
            'json' => ['terms' => 'Be nice.', 'termsTranslations' => ['fr' => 'Soyez sympa.']],
        ]);
        $this->assertResponseIsSuccessful();
        $client->request('POST', self::iri($ws).'/terms/sign', [
            'headers' => self::authHeaders(self::USER),
            'json' => [],
        ]);
        $this->assertResponseIsSuccessful();

        $response = $client->request('POST', self::iri($ws).'/flush', [
            'headers' => self::authHeaders(self::ADMIN),
            'json' => [],
        ]);
        $this->assertResponseIsSuccessful();
        $newId = $response->toArray()['id'];

        $response = $client->request('GET', self::iri($newId), [
            'headers' => self::authHeaders(self::USER),
        ]);
        $data = $response->toArray();
        $this->assertSame('Be nice.', $data['terms']['rawText']);
        $this->assertSame(['fr' => 'Soyez sympa.'], $data['terms']['translations']);
        // Signatures belong to the old workspace: they have to be renewed
        $this->assertSame(1, $data['terms']['version']);
        $this->assertFalse($data['terms']['signed']);
        $this->assertTrue($data['termsUnsigned']);
    }

    public static function flushAccessProvider(): array
    {
        return [
            // user, ACE mask (null = none), expected status
            'anonymous' => [null, null, 401],
            'user without access' => [self::USER, null, 403],
            'user with VIEW' => [self::USER, PermissionInterface::VIEW, 403],
            'user with DELETE' => [self::USER, PermissionInterface::VIEW | PermissionInterface::DELETE, 403],
            'user with EDIT' => [self::USER, PermissionInterface::VIEW | PermissionInterface::EDIT, 201],
            'admin' => [self::ADMIN, null, 201],
        ];
    }

    /**
     * @dataProvider flushAccessProvider
     */
    public function testFlushAccess(?string $userId, ?int $mask, int $expectedStatus): void
    {
        $ws = $this->createWs('ws', ['public' => true]);
        $id = $ws->getId();
        $this->createCollection(['workspace' => $ws, 'name' => 'Col']);
        if (null !== $mask) {
            $this->grantUser($userId, $ws, $mask);
        }

        static::createClient()->request('POST', self::iri($ws).'/flush', [
            'headers' => self::authHeaders($userId),
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame($expectedStatus);
        $em = self::getEntityManager();
        $em->clear();
        if (201 === $expectedStatus) {
            $this->assertNull($em->find(Workspace::class, $id));
        } else {
            $stored = $em->find(Workspace::class, $id);
            $this->assertNotNull($stored);
            $this->assertSame('ws', $stored->getSlug());
            $this->assertCount(1, $em->getRepository(Collection::class)->findBy(['workspace' => $id]));
        }
    }

    public function testFlushUnknownWorkspace(): void
    {
        static::createClient()->request('POST', '/workspaces/2f1c7f0e-0b0a-4b6e-9a51-3c1b4f8f0a99/flush', [
            'headers' => self::authHeaders(self::ADMIN),
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testFlushIgnoresPayload(): void
    {
        $this->markTestIncomplete('BUG: the flush operation (Workspace.php, Post /workspaces/{id}/flush) does not set deserialize: false: the body is denormalized through WorkspaceInput onto the workspace before FlushWorkspaceAction duplicates it, so {"slug": "x", "name": "y"} silently renames the flushed workspace');

        $ws = $this->createWs('ws', ['name' => 'Original', 'ownerId' => self::USER]);

        $response = static::createClient()->request('POST', self::iri($ws).'/flush', [
            'headers' => self::authHeaders(self::USER),
            'json' => ['name' => 'Hijacked', 'slug' => 'hijacked', 'ownerId' => self::OTHER],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('ws', $data['slug']);
        $this->assertSame(self::USER, $data['ownerId']);
        $this->assertSame('Original', $data['name']);
    }
}
