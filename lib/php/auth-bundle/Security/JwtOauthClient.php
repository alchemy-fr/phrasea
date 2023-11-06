<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

class JwtOauthClient implements JwtInterface
{
    final public const ROLE_OAUTH_CLIENT = 'ROLE_OAUTH_CLIENT';

    private readonly array $roles;

    public function __construct(
        private readonly string $jwt,
        private readonly string $clientId,
        private readonly array $scopes = [],
    )
    {
        $this->roles = array_map(fn (string $role): string => sprintf('ROLE_%s', strtoupper($role)), $this->scopes);
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return $this->clientId;
    }

    public function eraseCredentials(): void
    {
    }

    public function getJwt(): string
    {
        return $this->jwt;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }
}
