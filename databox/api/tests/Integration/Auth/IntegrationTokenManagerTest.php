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
    public function testRenewalIsDueOnlyWithinThreshold(): void
    {
        $manager = new IntegrationTokenManager($this->createMock(EntityManagerInterface::class));

        $token = $this->createToken(accessExpiresIn: 3600);

        $this->assertFalse($manager->isRenewalDue($token));
        $this->assertFalse($manager->isRenewalDue($token, 600));
        $this->assertTrue($manager->isRenewalDue($token, 7200));

        $expired = $this->createToken(accessExpiresIn: -10);
        $this->assertTrue($manager->isRenewalDue($expired));

        $noRefresh = $this->createToken(accessExpiresIn: -10, refreshToken: null);
        $this->assertFalse($manager->isRenewalDue($noRefresh));
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
        $this->assertFalse($manager->isRenewalDue($renewed));
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
