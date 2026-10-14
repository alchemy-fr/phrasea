<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Face;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFace;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * The faces page API: listing the detected faces and the named persons, naming a face.
 */
class AssetFaceTest extends AbstractDataboxTestCase
{
    private const string ROLE = 'edit_faces';

    public function testListingIsReservedToTheFaceEditors(): void
    {
        $client = static::createClient();
        $workspace = $this->createWorkspace(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $workspace->getId());
        $face = $this->createFace($workspace, 'Alice', AssetFace::IDENTITY_ORIGIN_USER);

        $client->request('GET', '/asset-faces');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);

        $client->request('GET', '/asset-faces', ['headers' => $this->headers(KeycloakClientTestMock::USER_UID)]);
        $this->assertResponseStatusCodeSame(403);
        $client->request('GET', '/asset-faces/identities', ['headers' => $this->headers(KeycloakClientTestMock::USER_UID)]);
        $this->assertResponseStatusCodeSame(403);
        $client->request('GET', '/asset-faces/'.$face->getId(), ['headers' => $this->headers(KeycloakClientTestMock::USER_UID)]);
        $this->assertResponseStatusCodeSame(403);

        // The role unlocks the faces of the workspaces the user can read
        $response = $client->request('GET', '/asset-faces', ['headers' => $this->headers(KeycloakClientTestMock::USER_UID, [self::ROLE])]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $response->toArray()['totalItems']);
        $member = $response->toArray()['member'][0];
        $this->assertSame($face->getId(), $member['id']);
        $this->assertSame('Alice', $member['identity']);
        $this->assertSame(AssetFace::IDENTITY_ORIGIN_USER, $member['identityOrigin']);
        $this->assertSame(['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.4], $member['box']);
        $this->assertSame($face->getAsset()->getId(), $member['asset']['id']);

        $client->request('GET', '/asset-faces/'.$face->getId(), ['headers' => $this->headers(KeycloakClientTestMock::USER_UID, [self::ROLE])]);
        $this->assertResponseIsSuccessful();

        // Admins need no role
        $response = $client->request('GET', '/asset-faces', ['headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID)]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $response->toArray()['totalItems']);

        // The role alone gives nothing on the workspaces the user cannot read
        $response = $client->request('GET', '/asset-faces', ['headers' => $this->headers(KeycloakClientTestMock::OTHER_USER_UID, [self::ROLE])]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $response->toArray()['totalItems']);
        $client->request('GET', '/asset-faces/'.$face->getId(), ['headers' => $this->headers(KeycloakClientTestMock::OTHER_USER_UID, [self::ROLE])]);
        $this->assertResponseStatusCodeSame(403);
        $client->request('GET', '/asset-faces', [
            'query' => ['workspace' => $workspace->getId()],
            'headers' => $this->headers(KeycloakClientTestMock::OTHER_USER_UID, [self::ROLE]),
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testFiltersAndIdentities(): void
    {
        $client = static::createClient();
        $workspace = $this->createWorkspace(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $other = $this->createWorkspace(['ownerId' => KeycloakClientTestMock::ADMIN_UID, 'name' => 'Other', 'slug' => 'other']);
        $headers = $this->headers(KeycloakClientTestMock::ADMIN_UID);

        $alice = $this->createFace($workspace, 'Alice', AssetFace::IDENTITY_ORIGIN_USER);
        $this->createFace($workspace, 'Alice', AssetFace::IDENTITY_ORIGIN_AUTO, 0.8, $alice->getAsset());
        $this->createFace($workspace, 'Bob', AssetFace::IDENTITY_ORIGIN_USER);
        $unnamed = $this->createFace($workspace, null, null);
        $this->createFace($other, 'Carol', AssetFace::IDENTITY_ORIGIN_USER);

        $list = function (array $query) use ($client, $headers): array {
            $response = $client->request('GET', '/asset-faces', ['query' => $query, 'headers' => $headers]);
            $this->assertResponseIsSuccessful();

            // Null values are left out of the payload
            return array_map(static fn (array $member): ?string => $member['identity'] ?? null, $response->toArray()['member']);
        };

        $this->assertEqualsCanonicalizing(['Alice', 'Alice', 'Bob', null, 'Carol'], $list([]));
        $this->assertEqualsCanonicalizing(['Alice', 'Alice', 'Bob', null], $list(['workspace' => $workspace->getId()]));
        $this->assertEqualsCanonicalizing(['Carol'], $list(['workspace' => '/workspaces/'.$other->getId()]));
        $this->assertEqualsCanonicalizing(['Alice', 'Alice'], $list(['identity' => 'Alice']));
        $this->assertEqualsCanonicalizing([null], $list(['identified' => 'false']));
        $this->assertEqualsCanonicalizing(['Alice', 'Alice', 'Bob', 'Carol'], $list(['identified' => 'true']));
        $this->assertEqualsCanonicalizing(['Alice'], $list(['origin' => AssetFace::IDENTITY_ORIGIN_AUTO]));
        $this->assertEqualsCanonicalizing(['Bob'], $list(['query' => 'bo']));
        $this->assertEqualsCanonicalizing(['Alice', 'Alice'], $list(['asset' => $alice->getAsset()->getId()]));
        $this->assertEqualsCanonicalizing([null], $list(['asset' => $unnamed->getAsset()->getId()]));

        $response = $client->request('GET', '/asset-faces', ['query' => ['limit' => 2, 'page' => 2], 'headers' => $headers]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(5, $response->toArray()['totalItems']);
        $this->assertCount(2, $response->toArray()['member']);

        $response = $client->request('GET', '/asset-faces/identities', ['headers' => $headers]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'identities' => [
                ['identity' => 'Alice', 'faces' => 2, 'userFaces' => 1],
                ['identity' => 'Bob', 'faces' => 1, 'userFaces' => 1],
                ['identity' => 'Carol', 'faces' => 1, 'userFaces' => 1],
            ],
            'unnamed' => 1,
        ], $response->toArray());

        $response = $client->request('GET', '/asset-faces/identities', [
            'query' => ['workspace' => $workspace->getId(), 'query' => 'ali'],
            'headers' => $headers,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'identities' => [
                ['identity' => 'Alice', 'faces' => 2, 'userFaces' => 1],
            ],
            'unnamed' => 1,
        ], $response->toArray());
    }

    public function testNamingAFace(): void
    {
        $client = static::createClient();
        $workspace = $this->createWorkspace(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $workspace->getId());
        $face = $this->createFace($workspace, null, null);
        $uri = '/asset-faces/'.$face->getId().'/identity';

        // The integration must be enabled in the workspace for the name to be applied
        $client->request('POST', $uri, ['json' => ['identity' => 'Alice'], 'headers' => $this->headers(KeycloakClientTestMock::USER_UID, [self::ROLE])]);
        $this->assertResponseStatusCodeSame(400);

        $this->enableIntegration($workspace);

        $client->request('POST', $uri, ['json' => ['identity' => 'Alice'], 'headers' => $this->headers(KeycloakClientTestMock::USER_UID)]);
        $this->assertResponseStatusCodeSame(403);

        $response = $client->request('POST', $uri, ['json' => ['identity' => ' Alice '], 'headers' => $this->headers(KeycloakClientTestMock::USER_UID, [self::ROLE])]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Alice', $response->toArray()['identity']);
        $this->assertSame(AssetFace::IDENTITY_ORIGIN_USER, $response->toArray()['identityOrigin']);
        $this->assertEquals(1.0, $response->toArray()['identityConfidence']);

        $response = $client->request('GET', '/asset-faces/identities', ['headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID)]);
        $this->assertSame(['identities' => [['identity' => 'Alice', 'faces' => 1, 'userFaces' => 1]], 'unnamed' => 0], $response->toArray());

        $response = $client->request('POST', $uri, ['json' => ['identity' => null], 'headers' => $this->headers(KeycloakClientTestMock::USER_UID, [self::ROLE])]);
        $this->assertResponseIsSuccessful();
        $this->assertNull($response->toArray()['identity']);
        $this->assertNull($response->toArray()['identityOrigin']);

        $client->request('POST', '/asset-faces/00000000-0000-0000-0000-000000000000/identity', ['json' => ['identity' => 'X'], 'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID)]);
        $this->assertResponseStatusCodeSame(404);
    }

    private function headers(string $userId, array $extraRoles = []): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId, $extraRoles),
        ];
    }

    private function createFace(Workspace $workspace, ?string $identity, ?string $origin, float $identityConfidence = 1.0, ?Asset $asset = null): AssetFace
    {
        $em = self::getEntityManager();

        // Public: readable by every user of the workspace (the role never grants more than the asset ACL)
        $asset ??= $this->createAsset(['workspace' => $workspace, 'public' => true]);

        $face = new AssetFace();
        $face->setAsset($asset);
        $face->setBox(['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.4]);
        $face->setConfidence(0.9);
        $face->setVector([1.0, 0.0]);
        $face->setModel('test');
        if (null !== $identity) {
            $face->setIdentity($identity, $origin, $identityConfidence);
        }
        $em->persist($face);
        $em->flush();

        return $face;
    }

    private function enableIntegration(Workspace $workspace): void
    {
        $em = self::getEntityManager();

        $integration = new WorkspaceIntegration();
        $integration->setPublic(true);
        $integration->setWorkspace($workspace);
        $integration->setName('Faces');
        $integration->setIntegration(FaceRecognitionIntegration::getName());
        $integration->setConfig([]);
        $integration->setOwnerId('custom_owner');
        $em->persist($integration);
        $em->flush();
    }
}
