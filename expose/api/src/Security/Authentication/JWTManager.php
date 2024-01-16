<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class JWTManager
{
    public function __construct(private string $signingKey, private string $baseUri, private int $ttl)
    {
    }

    public function signUri(string $uri, int $ttl = null): string
    {
        $config = $this->getConfig();
        $token = $config->builder()
            ->identifiedBy($uri)
            ->issuedBy($this->baseUri)
            ->issuedAt(new \DateTimeImmutable())
            ->expiresAt((new \DateTimeImmutable())->setTimestamp(time() + ($ttl ?? $this->ttl)))
            ->getToken($config->signer(), $config->signingKey());

        return implode('', [
            $uri,
            !str_contains($uri, '?') ? '?' : '&',
            'jwt=',
            $token->toString(),
        ]);
    }

    public function validateJWT(string $uri, string $jwt): void
    {
        $config = $this->getConfig();
        $token = $config->parser()->parse($jwt);
        assert($token instanceof UnencryptedToken);

        $uri = preg_replace('#([&?])jwt=.+$#', '', $uri);

        $config->setValidationConstraints(
            new Constraint\LooseValidAt(
                new SystemClock(new \DateTimeZone('UTC')),
                new \DateInterval('PT30S')
            ),
            new Constraint\IdentifiedBy($uri),
            new Constraint\IssuedBy($this->baseUri),
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
