<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Security\Impersonator;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\Functional\AbstractSearchTestCase;

class ImpersonationTest extends AbstractSearchTestCase
{
    public function testAdminActsWithTheTargetPermissions(): void
    {
        self::enableFixtures();
        $client = static::createClient();

        $response = $client->request('POST', '/baskets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'name' => 'My private basket',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];

        $adminHeaders = [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
        ];

        // Acting as the owner
        $client->request('GET', '/baskets/'.$id, [
            'headers' => [
                ...$adminHeaders,
                Impersonator::HEADER => KeycloakClientTestMock::USER_UID,
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);

        // Acting as another user: admin privileges are dropped
        $client->request('GET', '/baskets/'.$id, [
            'headers' => [
                ...$adminHeaders,
                Impersonator::HEADER => KeycloakClientTestMock::OTHER_USER_UID,
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonAdminCannotImpersonate(): void
    {
        $client = static::createClient();

        $client->request('GET', '/baskets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
                Impersonator::HEADER => KeycloakClientTestMock::ADMIN_UID,
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/impersonation/users', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminGetsTargetIdentity(): void
    {
        $client = static::createClient();

        $response = $client->request('GET', '/impersonation/users/'.KeycloakClientTestMock::OTHER_USER_UID, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertSame(KeycloakClientTestMock::OTHER_USER_UID, $data['id']);
        $this->assertSame(['databox', 'expose', 'uploader'], $data['roles']);
        $this->assertSame([], $data['groups']);

        // An impersonated request cannot reach the admin-only routes
        $client->request('GET', '/impersonation/users', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
                Impersonator::HEADER => KeycloakClientTestMock::ADMIN_UID,
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }
}
