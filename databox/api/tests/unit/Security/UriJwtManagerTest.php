<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use Alchemy\AuthBundle\Security\UriJwtManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UriJwtManagerTest extends TestCase
{
    private const string BASE_URI = 'https://api.test';
    private const string URI = 'https://api.test/assets/42/download';
    private const string KEY = '0123456789abcdef0123456789abcdef';

    public function testSignedUriIsValid(): void
    {
        $manager = new UriJwtManager(self::KEY, self::BASE_URI, 60);
        $signed = $manager->signUri(self::URI);

        $manager->validateUri(self::URI, substr($signed, strpos($signed, 'jwt=') + 4));
        $this->addToAssertionCount(1);
    }

    public function testTokenSignedWithAnotherKeyIsRejected(): void
    {
        $forger = new UriJwtManager('ffffffffffffffffffffffffffffffff', self::BASE_URI, 60);
        $jwt = $forger->createToken(self::URI);

        $this->expectException(AccessDeniedHttpException::class);
        new UriJwtManager(self::KEY, self::BASE_URI, 60)->validateJWT(self::URI, $jwt);
    }

    public function testUnsignedTokenIsRejected(): void
    {
        $encode = fn (array $data): string => rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $jwt = $encode(['typ' => 'JWT', 'alg' => 'none']).'.'.$encode([
            'jti' => self::URI,
            'iss' => self::BASE_URI,
            'iat' => time(),
            'exp' => time() + 60,
        ]).'.';

        $this->expectException(AccessDeniedHttpException::class);
        new UriJwtManager(self::KEY, self::BASE_URI, 60)->validateJWT(self::URI, $jwt);
    }
}
