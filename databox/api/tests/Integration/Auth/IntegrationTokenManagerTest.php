<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Entity\Integration\IntegrationToken;
use App\Integration\Auth\IntegrationTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class IntegrationTokenManagerTest extends TestCase
{
    public function testTokenExpiryHelpers(): void
    {
        $token = $this->createToken(accessExpiresIn: 3600);
        $this->assertTrue($token->hasRefreshToken());
        $this->assertFalse($token->isAccessTokenExpired());
        $this->assertFalse($token->isRefreshTokenExpiringWithin(3600));
        $this->assertTrue($token->isRefreshTokenExpiringWithin(2 * 86400));

        $expired = $this->createToken(accessExpiresIn: -10);
        $this->assertTrue($expired->isAccessTokenExpired());

        $noRefresh = $this->createToken(accessExpiresIn: -10, refreshToken: null);
        $this->assertFalse($noRefresh->hasRefreshToken());
    }

    public function testGetAccessTokenRenewsOnlyWhenAccessTokenIsExpired(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $manager = new IntegrationTokenManager($em);

        $valid = $this->createToken(accessExpiresIn: 3600);
        $this->assertSame('access-1', $manager->getAccessToken($valid, fn () => $this->fail('must not renew')));

        $expired = $this->createToken(accessExpiresIn: -10);
        $this->assertSame('access-2', $manager->getAccessToken($expired, fn (): array => [
            'access_token' => 'access-2',
            'refresh_token' => 'refresh-2',
            'expires_in' => 300,
        ]));
    }

    public function testRenewTokenPersistsTheNewTokenSet(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');
        $em->expects($this->never())->method('remove');

        $manager = new IntegrationTokenManager($em);
        $token = $this->createToken(accessExpiresIn: 60);

        $renewed = $manager->renewToken($token, function (string $refreshToken, IntegrationToken $t) use ($token): array {
            $this->assertSame('refresh-1', $refreshToken);
            $this->assertSame($token, $t);

            return [
                'access_token' => 'access-2',
                'refresh_token' => 'refresh-2',
                'expires_in' => 300,
                'refresh_expires_in' => 86400,
            ];
        });

        $this->assertSame($token, $renewed);
        $this->assertSame('access-2', $renewed->getToken()['access_token']);
        $this->assertSame('refresh-2', $renewed->getToken()['refresh_token']);
        $this->assertGreaterThan(time() + 86000, $renewed->getExpiresAt()->getTimestamp());
        $this->assertFalse($renewed->isAccessTokenExpired());
    }

    /**
     * @dataProvider revokedStatusProvider
     */
    public function testRevokedRefreshTokenIsRemoved(int $status): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove');
        $em->expects($this->once())->method('flush');

        $manager = new IntegrationTokenManager($em);
        $token = $this->createToken(accessExpiresIn: -10);

        $exception = new class($status) extends \RuntimeException implements ClientExceptionInterface {
            public function __construct(int $status)
            {
                parent::__construct('rejected', $status);
            }

            public function getResponse(): ResponseInterface
            {
                throw new \LogicException('not needed');
            }
        };

        $this->expectException(ClientExceptionInterface::class);
        $manager->renewToken($token, fn () => throw $exception);
    }

    public function revokedStatusProvider(): array
    {
        return [[400], [401]];
    }

    public function testServerErrorKeepsTheToken(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');

        $manager = new IntegrationTokenManager($em);
        $token = $this->createToken(accessExpiresIn: -10);

        $exception = new class extends \RuntimeException implements ClientExceptionInterface {
            public function __construct()
            {
                parent::__construct('unavailable', 503);
            }

            public function getResponse(): ResponseInterface
            {
                throw new \LogicException('not needed');
            }
        };

        $this->expectException(ClientExceptionInterface::class);
        $manager->renewToken($token, fn () => throw $exception);
    }

    private function createToken(int $accessExpiresIn, ?string $refreshToken = 'refresh-1'): IntegrationToken
    {
        $token = new IntegrationToken();
        $data = [
            'access_token' => 'access-1',
            'expires_at' => time() + $accessExpiresIn,
        ];
        if (null !== $refreshToken) {
            $data['refresh_token'] = $refreshToken;
        }
        $token->setToken($data);
        $token->setExpiresAt(new \DateTimeImmutable('+1 day'));

        return $token;
    }
}
