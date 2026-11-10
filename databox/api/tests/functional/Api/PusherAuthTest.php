<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\AssetExport;
use App\Model\UserData;
use App\Realtime\RealtimeChannel;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional coverage of the core Pusher/Sockudo channel-authorization endpoint
 * (POST /pusher/auth): a user may only authorize their own `private-user-{sub}`
 * channel (e.g. in-app notifications), and a channel bound to an entity is
 * granted to whoever may read it, anonymous visitors included.
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

    public function testAnonymousMayFollowAPublicAssetOfAPublicWorkspace(): void
    {
        $workspace = $this->createWorkspace(['public' => true, 'ownerId' => KeycloakClientTestMock::USER_UID]);
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'public' => true,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        $this->authorize(RealtimeChannel::asset($asset->getId()), null);
        $this->assertResponseStatusCodeSame(200);

        $this->authorize(RealtimeChannel::workspace($workspace->getId()), null);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testAnonymousCannotFollowPrivateObjects(): void
    {
        $publicWorkspace = $this->createWorkspace(['public' => true, 'slug' => 'public-ws']);
        $privateAsset = $this->createAsset(['workspace' => $publicWorkspace]);
        $privateWorkspace = $this->createWorkspace(['slug' => 'private-ws']);

        $this->authorize(RealtimeChannel::asset($privateAsset->getId()), null);
        $this->assertResponseStatusCodeSame(401);

        $this->authorize(RealtimeChannel::workspace($privateWorkspace->getId()), null);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testOnlyReadersMayFollowPrivateObjects(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => KeycloakClientTestMock::USER_UID]);
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        foreach ([
            RealtimeChannel::asset($asset->getId()),
            RealtimeChannel::workspace($workspace->getId()),
        ] as $channel) {
            $this->authorize($channel, KeycloakClientTestMock::USER_UID);
            $this->assertResponseStatusCodeSame(200, $channel);

            $this->authorize($channel, KeycloakClientTestMock::OTHER_USER_UID);
            $this->assertResponseStatusCodeSame(403, $channel);
        }
    }

    public function testOnlyTheOwnerMayFollowAnExport(): void
    {
        $em = self::getEntityManager();
        $export = new AssetExport();
        $export->setOwnerId(KeycloakClientTestMock::USER_UID);
        $export->setUserData(new UserData(KeycloakClientTestMock::USER_UID, []));
        $export->setAssets([]);
        $export->setRenditions([]);
        $em->persist($export);
        $em->flush();

        $this->authorize(RealtimeChannel::export($export->getId()), KeycloakClientTestMock::USER_UID);
        $this->assertResponseStatusCodeSame(200);

        $this->authorize(RealtimeChannel::export($export->getId()), KeycloakClientTestMock::OTHER_USER_UID);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testThreadIsGrantedOnItsObjectBeforeItExists(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => KeycloakClientTestMock::USER_UID]);
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        // Channel names are normalized: the thread key "asset:<id>" becomes "asset.<id>"
        $channel = RealtimeChannel::THREAD.'asset.'.$asset->getId();

        $this->authorize($channel, KeycloakClientTestMock::USER_UID);
        $this->assertResponseStatusCodeSame(200);

        $this->authorize($channel, KeycloakClientTestMock::OTHER_USER_UID);
        $this->assertResponseStatusCodeSame(403);
    }

    public static function getRejectedChannels(): array
    {
        $uuid = '4a1c3b6e-2f0d-4c55-9d7c-3b8e8a0b9f10';

        return [
            'former global channel' => ['assets', KeycloakClientTestMock::USER_UID],
            'public asset channel' => ['asset-'.$uuid, KeycloakClientTestMock::USER_UID],
            'public channel, anonymous' => ['assets', null],
            'unknown asset' => [RealtimeChannel::ASSET.$uuid, KeycloakClientTestMock::USER_UID],
            'invalid id' => [RealtimeChannel::WORKSPACE.'foo', KeycloakClientTestMock::USER_UID],
            'unknown thread object' => [RealtimeChannel::THREAD.'foo.'.$uuid, KeycloakClientTestMock::USER_UID],
            'unknown prefix' => ['private-foo-'.$uuid, KeycloakClientTestMock::USER_UID],
        ];
    }

    #[DataProvider('getRejectedChannels')]
    public function testRejectsChannel(string $channel, ?string $userId): void
    {
        $this->authorize($channel, $userId);
        $this->assertResponseStatusCodeSame(403);
    }

    private function authorize(string $channel, ?string $userId): void
    {
        $options = [
            'json' => [
                'socket_id' => '123.456',
                'channel_name' => $channel,
            ],
        ];
        if (null !== $userId) {
            $options['headers']['Authorization'] = 'Bearer '.KeycloakClientTestMock::getJwtFor($userId);
        }

        static::createClient()->request('POST', self::ENDPOINT, $options);
    }
}
