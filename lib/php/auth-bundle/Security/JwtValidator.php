<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token as TokenInterface;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\HasClaimWithValue;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Accepts the access tokens of the realm (not its ID or refresh tokens, signed by the same key)
 * meant for this API: obtained by its own client, or whose audience includes that client.
 */
final class JwtValidator implements JwtValidatorInterface
{
    /**
     * The "typ" claim Keycloak gives to the access tokens ("ID" for the ID tokens, "Refresh"…).
     */
    final public const string ACCESS_TOKEN_TYPE = 'Bearer';

    private readonly Validator $validator;
    private ?string $publicKey = null;

    /**
     * @param string $clientId    the client of the application itself (admin, Swagger UI, service account), always
     *                            accepted, and the audience required from the tokens of the other clients
     * @param int    $tokenLeeway clock skew tolerated on "exp", "nbf" and "iat", in seconds
     * @param string $appName     the application, naming the client scope giving the audience in the logs
     */
    public function __construct(
        private readonly KeycloakClient $keycloakClient,
        private readonly KeycloakUrlGenerator $keycloakUrlGenerator,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly string $clientId,
        private readonly int $tokenLeeway,
        private readonly string $appName,
    ) {
        $this->validator = new Validator();
    }

    public function isTokenValid(TokenInterface $token): bool
    {
        try {
            $this->validator->assert(
                $token,
                new SignedWith(
                    new Sha256(),
                    InMemory::plainText($this->getPublicKey())
                ),
                new IssuedBy($this->keycloakUrlGenerator->getRealmInfoUrl(false)),
                new LooseValidAt($this->clock, new \DateInterval(sprintf('PT%dS', $this->tokenLeeway))),
                new HasClaimWithValue('typ', self::ACCESS_TOKEN_TYPE),
            );
        } catch (RequiredConstraintsViolated $e) {
            $this->logger->debug('Invalid JWT: {reason}', [
                'reason' => $e->getMessage(),
            ]);

            return false;
        }

        $clientId = self::getClientId($token);
        if (null !== $clientId && $clientId === $this->clientId) {
            return true;
        }

        if (!$this->validator->validate($token, new PermittedFor($this->clientId))) {
            $this->logger->warning('Access token of the OAuth client "{client_id}" refused: its audience does not include "{audience}" (give the client the "{scope}" client scope in Keycloak)', [
                'client_id' => $clientId,
                'audience' => $this->clientId,
                'scope' => ApiAudience::getScopeName($this->appName),
            ]);

            return false;
        }

        return true;
    }

    /**
     * The client the token was issued to: "azp", else "client_id".
     */
    private static function getClientId(TokenInterface $token): ?string
    {
        if (!$token instanceof UnencryptedToken) {
            return null;
        }

        $claims = $token->claims();
        foreach (['azp', 'client_id'] as $claim) {
            $value = $claims->get($claim);
            if (is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
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
