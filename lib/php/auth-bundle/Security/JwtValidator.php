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
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Accepts the access tokens of the realm (not its ID or refresh tokens, signed by the same key)
 * obtained by an OAuth client allowed on this API.
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
     * @var string[]
     */
    private readonly array $allowedClientIds;

    /**
     * @param array                                       $allowedClientIds          client IDs whose tokens are accepted, besides $clientId
     * @param string                                      $clientId                  the client of the application itself (admin, Swagger UI…), always accepted
     * @param int                                         $tokenLeeway               clock skew tolerated on "exp", "nbf" and "iat", in seconds
     * @param iterable<AllowedClientIdsProviderInterface> $allowedClientIdsProviders
     */
    public function __construct(
        private readonly KeycloakClient $keycloakClient,
        private readonly KeycloakUrlGenerator $keycloakUrlGenerator,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        array $allowedClientIds,
        private readonly string $clientId,
        private readonly int $tokenLeeway,
        private readonly iterable $allowedClientIdsProviders = [],
    ) {
        $this->validator = new Validator();
        $this->allowedClientIds = array_values(array_filter(
            array_map(static fn (mixed $id): string => trim((string) $id), $allowedClientIds),
            static fn (string $id): bool => '' !== $id,
        ));
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

        // Checked once the token is known to be signed by the realm: the providers may query the IdP.
        $clientId = self::getClientId($token);
        if (null === $clientId || !$this->isClientAllowed($clientId)) {
            $this->logger->warning('Access token of the OAuth client "{client_id}" refused: the client is not allowed on this API', [
                'client_id' => $clientId,
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

    private function isClientAllowed(string $clientId): bool
    {
        if ($clientId === $this->clientId || in_array($clientId, $this->allowedClientIds, true)) {
            return true;
        }

        foreach ($this->allowedClientIdsProviders as $provider) {
            if ($provider->isClientAllowed($clientId)) {
                return true;
            }
        }

        return false;
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
