<?php

declare(strict_types=1);

namespace App\Security;

use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class JWTTokenManager
{
    public function __construct(private readonly string $signingKey, private readonly int $ttl)
    {
    }

    public function createToken(string $string, ?int $ttl = null): string
    {
        $config = $this->getConfig();
        $token = $config->builder()
            ->identifiedBy($string)
            ->issuedAt(new \DateTimeImmutable())
            ->expiresAt((new \DateTimeImmutable())->setTimestamp(time() + ($ttl ?? $this->ttl)))
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    public function validateToken(string $string, string $jwt): void
    {
        $config = $this->getConfig();
        $token = $config->parser()->parse($jwt);
        assert($token instanceof UnencryptedToken);

        $config->setValidationConstraints(
            new Constraint\LooseValidAt(
                new SystemClock(new \DateTimeZone('UTC')),
                new \DateInterval('PT30S')
            ),
            new Constraint\IdentifiedBy($string),
        );

        $constraints = $config->validationConstraints();

        if (!$config->validator()->validate($token, ...$constraints)) {
            throw new AccessDeniedHttpException('Invalid JWT');
        }
    }

    private function getConfig(): Configuration
    {
        return Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->signingKey)
        );
    }
}
