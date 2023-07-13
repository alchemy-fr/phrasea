<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

final class JwtValidator implements JwtValidatorInterface
{
    private readonly Validator $validator;
    private ?string $publicKey = null;

    public function __construct(
        private readonly AuthServiceClient $authServiceClient,
    )
    {
        $this->validator = new Validator();
    }

    public function isTokenValid(string $token): bool
    {
        $parser = new Parser(new JoseEncoder());

        $token = $parser->parse($token);

        return $this->validator->validate(
            $token,
            new SignedWith(
                new Sha256(),
                InMemory::plainText($this->getPublicKey())
            )
        );
    }

    private function getPublicKey(): string
    {
        if (null == $this->publicKey) {
        // TODO cache result
            $this->publicKey = sprintf('-----BEGIN PUBLIC KEY-----
%s
-----END PUBLIC KEY-----', $this->authServiceClient->getJwtPublicKey());
        }

        return $this->publicKey;
    }
}
