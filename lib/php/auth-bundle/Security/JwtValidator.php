<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
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
        private readonly KeycloakClient $keycloakClient,
        private readonly KeycloakUrlGenerator $keycloakUrlGenerator,
    ) {
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
            new IssuedBy($this->keycloakUrlGenerator->getRealmInfoUrl(false)),
        );
    }

    private function getPublicKey(): string
    {
        if (null == $this->publicKey) {
            $this->publicKey = sprintf('-----BEGIN PUBLIC KEY-----
%s
-----END PUBLIC KEY-----', trim($this->keycloakClient->getJwtPublicKey()));
        }

        return $this->publicKey;
    }
}
