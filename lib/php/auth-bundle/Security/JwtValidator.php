<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Alchemy\AuthBundle\Client\OAuthClient;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token as TokenInterface;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

final class JwtValidator implements JwtValidatorInterface
{
    private readonly Validator $validator;
    private ?string $publicKey = null;

    public function __construct(
        private readonly OAuthClient $authServiceClient,
        private readonly KeycloakUrlGenerator $keycloakUrlGenerator,
    )
    {
        $this->validator = new Validator();
    }

    public function isTokenValid(TokenInterface $token): bool
    {
        if ($token->isExpired(new \DateTimeImmutable())) {
            return false;
        }

        return $this->validator->validate(
            $token,
            new SignedWith(
                new Sha256(),
                InMemory::plainText($this->getPublicKey())
            ),
            new IssuedBy($this->keycloakUrlGenerator->getRealmInfo()),
        );
    }

    private function getPublicKey(): string
    {
        if (null == $this->publicKey) {
            $this->publicKey = sprintf('-----BEGIN PUBLIC KEY-----
%s
-----END PUBLIC KEY-----', trim($this->authServiceClient->getJwtPublicKey()));
        }

        return $this->publicKey;
    }
}
