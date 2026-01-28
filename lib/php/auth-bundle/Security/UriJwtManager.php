<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class UriJwtManager
{
    public function __construct(
        #[Autowire(env: 'APP_SECRET')]
        private string $signingKey,
        #[Autowire(param: 'alchemy_core.app_url')]
        private string $baseUri,
        #[Autowire(env: 'int:URI_JWT_TTL')]
        private int $ttl,
    ) {
    }

    public function createToken(string $identifier, ?int $ttl = null, array $extraClaims = []): string
    {
        $config = $this->getConfig();
        $builder = $config->builder()
            ->identifiedBy($identifier)
            ->issuedBy($this->baseUri)
            ->issuedAt(new \DateTimeImmutable())
            ->expiresAt((new \DateTimeImmutable())->setTimestamp(time() + ($ttl ?? $this->ttl)));

        foreach ($extraClaims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        $token = $builder->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    public function signUri(string $uri, ?int $ttl = null, array $extraClaims = []): string
    {
        $token = $this->createToken($uri, $ttl, $extraClaims);

        return implode('', [
            $uri,
            !str_contains($uri, '?') ? '?' : '&',
            'jwt=',
            $token,
        ]);
    }

    public function validateUri(string $uri, string $jwt): void
    {
        $this->validateJWT(preg_replace('#([&?])jwt=.+$#', '', $uri), $jwt);
    }

    public function validateJWT(string $identifier, string $jwt, array $extraConstraints = []): UnencryptedToken
    {
        $config = $this->getConfig();
        $token = $config->parser()->parse($jwt);
        assert($token instanceof UnencryptedToken);

        $config = $config->withValidationConstraints(
            new Constraint\LooseValidAt(
                new SystemClock(new \DateTimeZone('UTC')),
                new \DateInterval('PT30S')
            ),
            new Constraint\IdentifiedBy($identifier),
            new Constraint\IssuedBy($this->baseUri),
            ...$extraConstraints,
        );

        $constraints = $config->validationConstraints();

        if (!$config->validator()->validate($token, ...$constraints)) {
            throw new AccessDeniedHttpException('Invalid JWT');
        }

        return $token;
    }

    private function getConfig(): Configuration
    {
        return Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->signingKey)
        );
    }
}
