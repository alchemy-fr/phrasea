<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\AbstractDataboxTestCase;

/**
 * Functional coverage of the core Pusher/Soketi channel-authorization endpoint
 * (POST /pusher/auth) that makes per-user channels private: a user may only
 * authorize their own `private-user-{sub}` channel (e.g. in-app notifications).
 */
class PusherAuthTest extends AbstractDataboxTestCase
{
    private const string ENDPOINT = '/pusher/auth';

    public function testAuthorizesOwnPrivateChannel(): void
    {
        $client = static::createClient();

        $ownChannel = 'private-user-'.KeycloakClientTestMock::USER_UID;

        $response = $client->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'socket_id' => '123.456',
                'channel_name' => $ownChannel,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayHasKey('auth', $data);
        // Pusher auth token is "{appKey}:{hmac_sha256_hex}" — the signature is a
        // 64-char lowercase hex digest regardless of the configured credentials.
        self::assertMatchesRegularExpression('/:[0-9a-f]{64}$/', $data['auth']);
    }

    public function testCannotAuthorizeAnotherUsersChannel(): void
    {
        $client = static::createClient();

        $foreignChannel = 'private-user-'.KeycloakClientTestMock::OTHER_USER_UID;

        $client->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'socket_id' => '123.456',
                'channel_name' => $foreignChannel,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRejectsUnauthenticatedRequest(): void
    {
        $client = static::createClient();

        $client->request('POST', self::ENDPOINT, [
            'json' => [
                'socket_id' => '123.456',
                'channel_name' => 'private-user-'.KeycloakClientTestMock::USER_UID,
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRejectsMissingParameters(): void
    {
        $client = static::createClient();

        $client->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'socket_id' => '123.456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }
}
