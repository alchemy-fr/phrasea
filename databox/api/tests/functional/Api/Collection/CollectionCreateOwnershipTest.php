<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Test\Client;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * The owner of a new collection is set by its creator ("ownerId"):
 * it must not grant the creation itself.
 */
class CollectionCreateOwnershipTest extends AbstractDataboxTestCase
{
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;

    public function testWorkspaceReaderCannotCreateCollections(): void
    {
        $client = static::createClient();
        $workspace = $this->createMemberWorkspace();

        // Claiming the ownership of the new collection must not grant its creation
        $this->send($client, 'POST', '/collections', self::USER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'Squatted',
            'ownerId' => self::USER,
        ]);
        $this->assertResponseStatusCodeSame(403);

        // Unless within a collection of their own
        $parent = $this->createCollection(['ownerId' => self::USER]);
        $this->send($client, 'POST', '/collections', self::USER, [
            'parent' => '/collections/'.$parent->getId(),
            'name' => 'Child',
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    /**
     * A workspace of the admin, the user being one of its readers (and more if $extraPermissions).
     */
    private function createMemberWorkspace(int $extraPermissions = 0): Workspace
    {
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::ADMIN]);
        $this->grantUserOnObject(self::USER, $workspace, PermissionInterface::VIEW | $extraPermissions);

        return $workspace;
    }

    private function send(Client $client, string $method, string $uri, string $userId, array $json): void
    {
        $client->request($method, $uri, [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId)],
            'json' => $json,
        ]);
    }
}
