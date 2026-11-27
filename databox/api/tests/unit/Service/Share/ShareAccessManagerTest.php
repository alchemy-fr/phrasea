<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Share;

use App\Entity\Core\Share;
use App\Model\ShareTokenAccessEnum;
use App\Service\Share\ShareAccessManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ShareAccessManagerTest extends TestCase
{
    private const string SECRET = 'test-secret';
    private const string PASSWORD = 's3cr3t!';

    private function createManager(?Request $request = null, string $secret = self::SECRET): ShareAccessManager
    {
        $requestStack = new RequestStack();
        if (null !== $request) {
            $requestStack->push($request);
        }

        return new ShareAccessManager($requestStack, $secret);
    }

    private static function createShare(?string $password = self::PASSWORD): Share
    {
        $share = new Share();
        $share->setPassword($password);

        return $share;
    }

    private static function request(Share $share, ?string $access = null, ?string $token = null): Request
    {
        return new Request(array_filter([
            'token' => $token ?? $share->getToken(),
            'access' => $access,
        ], static fn (?string $v): bool => null !== $v));
    }

    public function testTheAccessTokenOpensItsShareFor24Hours(): void
    {
        $manager = $this->createManager();
        $share = self::createShare();

        $access = $manager->createAccessToken($share);
        $this->assertEqualsWithDelta(time() + ShareAccessManager::ACCESS_TTL, $access['expiresAt']->getTimestamp(), 2);
        $this->assertMatchesRegularExpression('/^\d+\.[A-Za-z0-9_-]+$/', $access['access']);

        $this->assertTrue($manager->isAccessTokenValid($share, $access['access']));
        $this->assertFalse($manager->isAccessTokenValid(self::createShare(), $access['access']), 'Another share');
        $this->assertFalse($this->createManager(secret: 'other')->isAccessTokenValid($share, $access['access']), 'Another secret');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedAccessProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'no signature' => ['12345'];
        yield 'no expiration' => ['.abc'];
        yield 'not a timestamp' => ['abc.def'];
        yield 'forged signature' => [(time() + 3600).'.forged'];
    }

    #[DataProvider('malformedAccessProvider')]
    public function testAMalformedAccessTokenIsRejected(string $access): void
    {
        $this->assertFalse($this->createManager()->isAccessTokenValid(self::createShare(), $access));
    }

    public function testAnExpiredAccessTokenIsRejected(): void
    {
        $share = self::createShare();
        $expiresAt = time() - 1;
        $signature = hash_hmac('sha256', $share->getId().'|'.$expiresAt.'|'.$share->getPasswordHash(), self::SECRET, true);
        $access = $expiresAt.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $this->assertFalse($this->createManager()->isAccessTokenValid($share, $access));
    }

    public function testChangingOrRemovingThePasswordRevokesTheAccessTokens(): void
    {
        $manager = $this->createManager();
        $share = self::createShare();
        $access = $manager->createAccessToken($share)['access'];

        $share->setPassword('another one');
        $this->assertFalse($manager->isAccessTokenValid($share, $access));

        $share->setPassword(null);
        $this->assertFalse($manager->isAccessTokenValid($share, $access));
    }

    public function testTheTokenOpensAShareWithoutPassword(): void
    {
        $share = self::createShare(null);

        $this->assertSame(ShareTokenAccessEnum::Granted, $this->createManager(self::request($share))->resolveTokenAccess($share));
        $this->assertSame(ShareTokenAccessEnum::Denied, $this->createManager(self::request($share, token: 'wrong'))->resolveTokenAccess($share));
        $this->assertSame(ShareTokenAccessEnum::Denied, $this->createManager(new Request())->resolveTokenAccess($share));
        $this->assertSame(ShareTokenAccessEnum::Denied, $this->createManager()->resolveTokenAccess($share), 'No request');
        $this->assertSame(ShareTokenAccessEnum::Denied, $this->createManager(new Request(['token' => [$share->getToken()]]))->resolveTokenAccess($share));
    }

    public function testAProtectedShareRequiresTheAccessToken(): void
    {
        $share = self::createShare();
        $access = $this->createManager()->createAccessToken($share)['access'];

        $this->assertSame(ShareTokenAccessEnum::PasswordRequired, $this->createManager(self::request($share))->resolveTokenAccess($share));
        $this->assertSame(ShareTokenAccessEnum::PasswordRequired, $this->createManager(self::request($share, 'forged'))->resolveTokenAccess($share));
        $this->assertSame(ShareTokenAccessEnum::Granted, $this->createManager(self::request($share, $access))->resolveTokenAccess($share));
        // The access token never replaces the token of the link
        $this->assertSame(ShareTokenAccessEnum::Denied, $this->createManager(self::request($share, $access, 'wrong'))->resolveTokenAccess($share));
    }

    /**
     * @return iterable<string, array{\Closure(Share): void}>
     */
    public static function unavailableShareProvider(): iterable
    {
        yield 'disabled' => [static fn (Share $share) => $share->setEnabled(false)];
        yield 'expired' => [static fn (Share $share) => $share->setExpiresAt(new \DateTimeImmutable('-1 minute'))];
        yield 'not started yet' => [static fn (Share $share) => $share->setStartsAt(new \DateTimeImmutable('+1 hour'))];
    }

    #[DataProvider('unavailableShareProvider')]
    public function testAnUnavailableShareIsDenied(\Closure $setUp): void
    {
        $share = self::createShare();
        $setUp($share);
        $access = $this->createManager()->createAccessToken($share)['access'];

        $this->assertSame(ShareTokenAccessEnum::Denied, $this->createManager(self::request($share, $access))->resolveTokenAccess($share));
    }

    public function testTheAccessTokenIsReadFromTheRequest(): void
    {
        $share = self::createShare();

        $this->assertSame('abc', $this->createManager(self::request($share, 'abc'))->getRequestAccessToken());
        $this->assertNull($this->createManager(self::request($share))->getRequestAccessToken());
        $this->assertNull($this->createManager()->getRequestAccessToken());
    }
}
