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

readonly class JWTTokenManager
{
    public function __construct(private string $signingKey, private int $ttl)
    {
    }

    public function createToken(string $string, ?int $ttl = null, array $extraClaims = []): string
    {
        $config = $this->getConfig();
        $builder = $config->builder()
            ->identifiedBy($string)
            ->issuedAt(new \DateTimeImmutable())
            ->expiresAt((new \DateTimeImmutable())->setTimestamp(time() + ($ttl ?? $this->ttl)));

        foreach ($extraClaims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        $token = $builder->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    public function validateToken(string $string, string $jwt): UnencryptedToken
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
