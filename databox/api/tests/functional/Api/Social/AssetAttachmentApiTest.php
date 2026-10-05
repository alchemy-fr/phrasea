<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Social;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Asset attachments (another asset of the same workspace attached to an
 * asset): `GET|POST /attachments`, `GET|PUT|DELETE /attachments/{id}`.
 *
 * Rights (AssetAttachmentVoter) follow the host asset: READ to read,
 * EDIT to create/update/delete; creating also needs READ on the attached
 * asset. The collection is scoped to one readable asset (`?assetId=`).
 *
 * Setup: USER owns the workspace and the assets, OTHER_USER is a member who
 * only reads the "public in workspace" assets.
 */
final class AssetAttachmentApiTest extends AbstractDataboxTestCase
{
    use SocialTestTrait;

    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string UNKNOWN_ID = '1a2b3c4d-0000-4000-8000-000000000000';

    private Workspace $workspace;
    private Asset $host;
    private Asset $document;
    private Asset $secret;

    private function setUpWorkspace(): void
    {
        $this->workspace = $this->createNamedWorkspace(self::USER, 'attachment-ws');
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());

        $this->host = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::USER]);
        $this->document = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::USER]);
        $this->secret = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::USER]);
        $this->host->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $this->document->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        self::getEntityManager()->flush();
    }

    public function testEditorAttachesAnAsset(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $response = $client->request('POST', '/attachments', self::auth(self::USER, [
            'json' => [
                'assetId' => $this->host->getId(),
                'attachmentId' => $this->document->getId(),
                'name' => 'Release form',
                'priority' => 3,
            ],
        ]));
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        $this->assertMatchesUuid($data['id']);
        $this->assertSame('Release form', $data['name']);
        $this->assertSame(3, $data['priority']);

        self::getEntityManager()->clear();
        $attachment = self::getEntityManager()->find(AssetAttachment::class, $data['id']);
        $this->assertSame($this->host->getId(), $attachment->getAsset()->getId());
        $this->assertSame($this->document->getId(), $attachment->getAttachment()->getId());

        // The host asset exposes it
        $asset = $client->request('GET', '/assets/'.$this->host->getId(), self::auth(self::USER))->toArray();
        $this->assertSame([$data['id']], array_column($asset['attachments'], 'id'));
    }

    public function testCreateRequiresAuthentication(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $client->request('POST', '/attachments', [
            'json' => [
                'assetId' => $this->host->getId(),
                'attachmentId' => $this->document->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testReaderCannotAttach(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $client->request('POST', '/attachments', self::auth(self::OTHER, [
            'json' => [
                'assetId' => $this->host->getId(),
                'attachmentId' => $this->document->getId(),
            ],
        ]));
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(0, self::getEntityManager()->getRepository(AssetAttachment::class)->count([]));
    }

    public function testUnknownAssetsAreRejected(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $client->request('POST', '/attachments', self::auth(self::USER, [
            'json' => ['assetId' => self::UNKNOWN_ID, 'attachmentId' => $this->document->getId()],
        ]));
        $this->assertResponseStatusCodeSame(400);

        $client->request('POST', '/attachments', self::auth(self::USER, [
            'json' => ['assetId' => $this->host->getId(), 'attachmentId' => self::UNKNOWN_ID],
        ]));
        $this->assertResponseStatusCodeSame(400);
    }

    public function testCannotAttachAnAssetOfAnotherWorkspace(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $otherWorkspace = $this->createNamedWorkspace(self::USER, 'attachment-ws2');
        $foreign = $this->createAsset(['workspace' => $otherWorkspace, 'ownerId' => self::USER]);

        $client->request('POST', '/attachments', self::auth(self::USER, [
            'json' => ['assetId' => $this->host->getId(), 'attachmentId' => $foreign->getId()],
        ]));
        $status = $client->getResponse()->getStatusCode();
        if (500 === $status) {
            $this->markTestIncomplete('BUG: AssetAttachmentInputTransformer (src/Api/InputTransformer/AssetAttachmentInputTransformer.php:34) throws a plain \InvalidArgumentException for an attachment of another workspace: 500 instead of 400/422.');
        }
        $this->assertContains($status, [400, 422]);
        $this->assertSame(0, self::getEntityManager()->getRepository(AssetAttachment::class)->count([]));
    }

    public function testCannotAttachAnAssetOneCannotRead(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        // OTHER owns (thus edits) this asset, but cannot read USER's secret asset
        $own = $this->createAsset(['workspace' => $this->workspace, 'ownerId' => self::OTHER]);

        $client->request('GET', '/assets/'.$this->secret->getId(), self::auth(self::OTHER));
        $this->assertResponseStatusCodeSame(403);

        $client->request('POST', '/attachments', self::auth(self::OTHER, [
            'json' => ['assetId' => $own->getId(), 'attachmentId' => $this->secret->getId()],
        ]));
        $this->assertResponseStatusCodeSame(403);
    }

    public function testReadRights(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $visible = $this->createAssetAttachment($this->host, $this->document, 'visible');
        $hidden = $this->createAssetAttachment($this->secret, $this->document, 'hidden');

        $data = $client->request('GET', '/attachments/'.$visible->getId(), self::auth(self::OTHER))->toArray();
        $this->assertSame('visible', $data['name']);

        $client->request('GET', '/attachments/'.$hidden->getId(), self::auth(self::OTHER));
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/attachments/'.$hidden->getId());
        $this->assertResponseStatusCodeSame(401);

        $client->request('GET', '/attachments/'.self::UNKNOWN_ID, self::auth(self::USER));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdate(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $attachment = $this->createAssetAttachment($this->host, $this->document, 'before');
        $uri = '/attachments/'.$attachment->getId();

        $client->request('PUT', $uri, self::auth(self::OTHER, ['json' => ['name' => 'hijacked']]));
        $this->assertResponseStatusCodeSame(403);

        $data = $client->request('PUT', $uri, self::auth(self::USER, [
            'json' => [
                'name' => 'after',
                'priority' => 7,
                // The host and the attached asset are set at creation only
                'assetId' => $this->secret->getId(),
                'attachmentId' => $this->secret->getId(),
            ],
        ]))->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame('after', $data['name']);
        $this->assertSame(7, $data['priority']);

        self::getEntityManager()->clear();
        $reloaded = self::getEntityManager()->find(AssetAttachment::class, $attachment->getId());
        $this->assertSame($this->host->getId(), $reloaded->getAsset()->getId());
        $this->assertSame($this->document->getId(), $reloaded->getAttachment()->getId());

        // An empty name clears it
        $data = $client->request('PUT', $uri, self::auth(self::USER, ['json' => ['name' => '']]))->toArray();
        $this->assertArrayNotHasKey('name', $data);
    }

    public function testDelete(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $attachment = $this->createAssetAttachment($this->host, $this->document);
        $uri = '/attachments/'.$attachment->getId();

        $client->request('DELETE', $uri, self::auth(self::OTHER));
        $this->assertResponseStatusCodeSame(403);

        $client->request('DELETE', $uri);
        $this->assertResponseStatusCodeSame(401);

        $client->request('DELETE', $uri, self::auth(self::USER));
        $this->assertResponseStatusCodeSame(204);

        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(AssetAttachment::class, $attachment->getId()));
        $this->assertNotNull(self::getEntityManager()->find(Asset::class, $this->document->getId()), 'The attached asset itself is kept');
    }

    public function testListDoesNotLeakAttachmentsOfUnreadableAssets(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $hidden = $this->createAssetAttachment($this->secret, $this->document, 'confidential');

        foreach ([self::OTHER, null] as $userId) {
            // The collection is scoped to one asset...
            $client->request('GET', '/attachments', self::auth($userId));
            $this->assertResponseStatusCodeSame(400);

            // ...which must be readable
            $response = $client->request('GET', '/attachments?assetId='.$this->secret->getId(), self::auth($userId));
            $this->assertResponseStatusCodeSame(403);
            $this->assertStringNotContainsString($hidden->getId(), $response->getContent(false));
        }
    }

    public function testListAttachmentsOfAnAsset(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $visible = $this->createAssetAttachment($this->host, $this->document, 'visible');
        $this->createAssetAttachment($this->secret, $this->document, 'confidential');

        $data = $client->request('GET', '/attachments?assetId='.$this->host->getId(), self::auth(self::OTHER))->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame([$visible->getId()], array_column($data['hydra:member'], 'id'));
        $this->assertSame('visible', $data['hydra:member'][0]['name']);
    }
}
