<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Social;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Share;
use App\Entity\Core\Workspace;
use App\Model\AuditActorTypeEnum;
use App\Model\AuditLogActionEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\AuditLog\AuditLogTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A share link protected by a password: `POST /s/{id}/unlock` exchanges the
 * password for an access token, passed as `access` to `GET /shares/{id}/public`.
 * A protected share gives no permalink to its files.
 */
final class SharePasswordTest extends AbstractDataboxTestCase
{
    use SocialTestTrait;
    use AuditLogTestTrait;

    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string PASSWORD = 's3cr3t!';
    private const string PREVIEW_PATH = 'shares/preview.jpg';
    private const string ATTACHMENT_PREVIEW_PATH = 'shares/contract-preview.jpg';

    private Workspace $workspace;
    private Asset $asset;
    private RenditionDefinition $definition;
    private AssetAttachment $attachment;

    private function setUpWorkspace(): void
    {
        $this->workspace = $this->createNamedWorkspace(self::USER, 'password-share-ws');
        $this->asset = $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::USER,
        ]);
        $this->definition = $this->createRenditionDefinition($this->workspace, 'web', true);
        $this->createRendition($this->asset, $this->definition, $this->createStorageFile($this->workspace, self::PREVIEW_PATH));

        $attached = $this->createAsset([
            'workspace' => self::managed($this->workspace),
            'ownerId' => self::USER,
        ]);
        $attachmentDefinition = $this->createRenditionDefinition($this->workspace, 'attachment-preview', true);
        $this->createRendition($attached, $attachmentDefinition, $this->createStorageFile($this->workspace, self::ATTACHMENT_PREVIEW_PATH));
        $this->attachment = $this->createAssetAttachment($this->asset, $attached, 'Contract');
        // The in-memory asset does not know its new attachment
        self::getEntityManager()->clear();
    }

    private function createProtectedShare(array $options = []): Share
    {
        return $this->createShare(self::USER, [$this->asset], $options + ['password' => self::PASSWORD]);
    }

    private static function publicUri(Share $share, ?string $access = null, ?string $token = null): string
    {
        return sprintf('/shares/%s/public?token=%s', $share->getId(), $token ?? $share->getToken())
            .(null !== $access ? '&access='.rawurlencode($access) : '');
    }

    private static function unlockUri(Share $share, ?string $token = null): string
    {
        return sprintf('/s/%s/unlock?token=%s', $share->getId(), $token ?? $share->getToken());
    }

    private static function renditionUri(Share $share, Asset $asset, RenditionDefinition $definition, ?string $access = null): string
    {
        return sprintf('/s/%s/a/%s/r/%s?token=%s', $share->getId(), $asset->getId(), $definition->getId(), $share->getToken())
            .(null !== $access ? '&access='.rawurlencode($access) : '');
    }

    private static function attachmentUri(Share $share, AssetAttachment $attachment, ?string $access = null): string
    {
        return sprintf('/s/%s/a/%s?token=%s', $share->getId(), $attachment->getId(), $share->getToken())
            .(null !== $access ? '&access='.rawurlencode($access) : '');
    }

    private function unlock($client, Share $share, string $password = self::PASSWORD): string
    {
        $response = $client->request('POST', self::unlockUri($share), ['json' => ['password' => $password]]);
        $this->assertResponseIsSuccessful();

        return $response->toArray()['access'];
    }

    private function assertPasswordRequired($client, string $uri): void
    {
        $response = $client->request('GET', $uri);
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(['error' => 'share_password_required'], $response->toArray(false));
    }

    public function testThePasswordIsStoredHashedAndNeverOutput(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createShare(self::USER, [$this->asset]);
        $this->purgeLogs();

        $response = $client->request('PATCH', '/shares/'.$share->getId(), self::patchOptions(self::USER, [
            'json' => ['password' => self::PASSWORD],
        ]));
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['hasPassword']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('passwordHash', $data);

        $hash = self::reload($share)->getPasswordHash();
        $this->assertNotNull($hash);
        $this->assertTrue(password_verify(self::PASSWORD, $hash));
        $this->assertStringNotContainsString($hash, $response->getContent());
        $this->assertStringNotContainsString(self::PASSWORD, $response->getContent());

        $response = $client->request('GET', '/shares/'.$share->getId(), self::auth(self::USER));
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString($hash, $response->getContent());

        $logs = json_encode(array_map(static fn ($log): array => $log->getData(), $this->findLogs(objectId: $share->getId())));
        $this->assertStringNotContainsString($hash, $logs);
        $this->assertStringNotContainsString(self::PASSWORD, $logs);

        // Not protected
        $other = $this->createShare(self::USER, [$this->asset]);
        $data = $client->request('GET', '/shares/'.$other->getId(), self::auth(self::USER))->toArray();
        $this->assertFalse($data['hasPassword']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPasswordProvider(): iterable
    {
        yield 'too short' => ['abc'];
        yield 'too long' => [str_repeat('a', 256)];
    }

    #[DataProvider('invalidPasswordProvider')]
    public function testThePasswordLengthIsChecked(string $password): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createShare(self::USER, [$this->asset]);

        $client->request('PATCH', '/shares/'.$share->getId(), self::patchOptions(self::USER, [
            'json' => ['password' => $password],
        ]));
        $this->assertResponseStatusCodeSame(400);

        $client->request('POST', '/shares', self::auth(self::USER, [
            'json' => [
                'assets' => [self::assetIri($this->asset)],
                'password' => $password,
            ],
        ]));
        $this->assertResponseStatusCodeSame(400);

        $this->assertNull(self::reload($share)->getPasswordHash());
    }

    public function testCreateAProtectedShare(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $data = $client->request('POST', '/shares', self::auth(self::USER, [
            'json' => [
                'assets' => [self::assetIri($this->asset)],
                'password' => self::PASSWORD,
            ],
        ]))->toArray();
        $this->assertResponseStatusCodeSame(201);
        $this->assertTrue($data['hasPassword']);
        // The owner gets the temporary URLs of the storage, no permalink
        $this->assertNotEmpty($data['alternateUrls']);
        foreach ($data['alternateUrls'] as $url) {
            $this->assertStringNotContainsString('/s/'.$data['id'].'/', $url['url']);
        }
    }

    public function testThePublicShareRequiresThePassword(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createProtectedShare();

        $this->assertPasswordRequired($client, self::publicUri($share));
        $this->assertPasswordRequired($client, self::publicUri($share, 'forged'));
        $this->assertPasswordRequired($client, self::publicUri($share, (time() + 3600).'.forged'));

        // Expired
        $hash = self::reload($share)->getPasswordHash();
        $this->assertPasswordRequired($client, self::publicUri($share, self::forgeAccess($share, $hash, time() - 1)));
        // The same signature, valid until then, opens the share: the test forges it right
        $client->request('GET', self::publicUri($share, self::forgeAccess($share, $hash, time() + 60)));
        $this->assertResponseIsSuccessful();

        // Without the token, nothing changes
        $client->request('GET', self::publicUri($share, null, 'wrong'));
        $this->assertResponseStatusCodeSame(401);
        // ...nor through the other read operation
        $client->request('GET', sprintf('/shares/%s?token=%s', $share->getId(), $share->getToken()));
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAWrongPasswordIsCountedAndLogged(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createProtectedShare(['name' => 'Protected']);
        $this->purgeLogs();

        $response = $client->request('POST', self::unlockUri($share), ['json' => ['password' => 'wrong', 'name' => 'hijacked']]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(['error' => 'invalid_password'], $response->toArray(false));

        $log = $this->findLog(AuditLogActionEnum::SharePasswordFailed, $share->getId());
        $this->assertSame(AuditActorTypeEnum::Anonymous, $log->getActorType());
        $this->assertNull($log->getUserId());
        $this->assertNotEmpty($log->getIp());
        $this->assertSame('Protected', $log->getObjectLabel());
        $this->assertSame($this->workspace->getId(), $log->getWorkspaceId());
        $this->assertSame(['remainingAttempts' => 9], $log->getData());

        // The body is never written to the share
        $reloaded = self::reload($share);
        $this->assertSame('Protected', $reloaded->getName());
        $this->assertTrue($reloaded->isPasswordValid(self::PASSWORD));

        // Without password
        $client->request('POST', self::unlockUri($share), ['json' => []]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheAttemptsAreLimited(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createProtectedShare();
        $this->purgeLogs();

        for ($i = 0; $i < 10; ++$i) {
            $client->request('POST', self::unlockUri($share), ['json' => ['password' => 'wrong']]);
            $this->assertResponseStatusCodeSame(403);
        }

        $response = $client->request('POST', self::unlockUri($share), ['json' => ['password' => self::PASSWORD]]);
        $this->assertResponseStatusCodeSame(429);
        $this->assertSame(['error' => 'too_many_attempts'], $response->toArray(false));
        $this->assertGreaterThan(0, (int) ($response->getHeaders(false)['retry-after'][0] ?? 0));

        // The refused attempts are not logged
        $this->assertCount(10, $this->findLogs(AuditLogActionEnum::SharePasswordFailed, $share->getId()));
    }

    public function testTheRightPasswordOpensTheShare(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createProtectedShare();

        $response = $client->request('POST', self::unlockUri($share), ['json' => ['password' => self::PASSWORD]]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $expiresAt = new \DateTimeImmutable($data['expiresAt']);
        $this->assertEqualsWithDelta(time() + 86400, $expiresAt->getTimestamp(), 60);

        $data = $client->request('GET', self::publicUri($share, $data['access']))->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $data['assets']);

        $this->assertCount(1, $data['alternateUrls']);
        $this->assertStringContainsString(self::PREVIEW_PATH, $data['alternateUrls'][0]['url']);
        $this->assertCount(1, $data['attachments']);
        $this->assertStringContainsString(self::ATTACHMENT_PREVIEW_PATH, $data['attachments'][0]['url']);
        foreach ([...$data['alternateUrls'], ...$data['attachments']] as $item) {
            $this->assertStringNotContainsString('/s/'.$share->getId().'/', $item['url'], 'No permalink');
        }
    }

    public function testChangingThePasswordRevokesTheAccess(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createProtectedShare();
        $access = $this->unlock($client, $share);

        $client->request('PATCH', '/shares/'.$share->getId(), self::patchOptions(self::USER, [
            'json' => ['password' => 'another one'],
        ]));
        $this->assertResponseIsSuccessful();

        $this->assertPasswordRequired($client, self::publicUri($share, $access));
        $client->request('GET', self::publicUri($share, $this->unlock($client, $share, 'another one')));
        $this->assertResponseIsSuccessful();
    }

    public function testAProtectedShareGivesNoPermalink(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createProtectedShare();
        $access = $this->unlock($client, $share);

        foreach ([
            [self::renditionUri($share, $this->asset, $this->definition), []],
            [self::renditionUri($share, $this->asset, $this->definition, $access), []],
            [self::renditionUri($share, $this->asset, $this->definition), self::auth(self::USER)],
            [self::attachmentUri($share, $this->attachment), []],
            [self::attachmentUri($share, $this->attachment, $access), []],
            [self::attachmentUri($share, $this->attachment), self::auth(self::USER)],
        ] as [$uri, $options]) {
            $client->request('GET', $uri, $options);
            $this->assertResponseStatusCodeSame(404, $uri);
        }

        // Without password, the share is open again, permalinks included
        $data = $client->request('PATCH', '/shares/'.$share->getId(), self::patchOptions(self::USER, [
            'json' => ['password' => null],
        ]))->toArray();
        $this->assertFalse($data['hasPassword']);

        $data = $client->request('GET', self::publicUri($share))->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(sprintf('/s/%s/a/', $share->getId()), $data['alternateUrls'][0]['url']);

        $client->request('GET', self::renditionUri($share, $this->asset, $this->definition));
        $this->assertResponseStatusCodeSame(302);
        $client->request('GET', self::attachmentUri($share, $this->attachment));
        $this->assertResponseStatusCodeSame(302);
    }

    public function testTheOwnerDoesNotNeedThePassword(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createProtectedShare();

        $data = $client->request('GET', self::publicUri($share), self::auth(self::USER))->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $data['assets']);
        $this->assertStringContainsString(self::PREVIEW_PATH, $data['alternateUrls'][0]['url']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ?string}>
     */
    public static function unavailableUnlockProvider(): iterable
    {
        yield 'wrong token' => [[], 'wrong'];
        yield 'disabled' => [['enabled' => false], null];
        yield 'expired' => [['expiresAt' => '-1 minute'], null];
        yield 'not started yet' => [['startsAt' => '+1 hour'], null];
        yield 'without password' => [['password' => null], null];
    }

    #[DataProvider('unavailableUnlockProvider')]
    public function testUnlockingAnUnavailableShareIsNotFound(array $options, ?string $token): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        foreach (['startsAt', 'expiresAt'] as $date) {
            if (isset($options[$date])) {
                $options[$date] = new \DateTimeImmutable($options[$date]);
            }
        }
        $share = array_key_exists('password', $options)
            ? $this->createShare(self::USER, [$this->asset], $options)
            : $this->createProtectedShare($options);
        $this->purgeLogs();

        $client->request('POST', self::unlockUri($share, $token), ['json' => ['password' => self::PASSWORD]]);
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame([], $this->findLogs(AuditLogActionEnum::SharePasswordFailed));
    }

    public function testUnlockingAnUnknownShareIsNotFound(): void
    {
        $client = static::createClient();

        $client->request('POST', '/s/1a2b3c4d-0000-4000-8000-000000000000/unlock?token=x', ['json' => ['password' => self::PASSWORD]]);
        $this->assertResponseStatusCodeSame(404);
    }

    private static function reload(Share $share): Share
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(Share::class, $share->getId());
    }

    private static function forgeAccess(Share $share, string $passwordHash, int $expiresAt): string
    {
        $secret = (string) self::getContainer()->getParameter('kernel.secret');
        $signature = hash_hmac('sha256', $share->getId().'|'.$expiresAt.'|'.$passwordHash, $secret, true);

        return $expiresAt.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
