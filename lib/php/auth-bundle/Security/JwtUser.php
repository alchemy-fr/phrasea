<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

class JwtUser implements JwtInterface, JwtUserInterface
{
    /**
     * @deprecated Use IS_AUTHENTICATED_FULLY instead
     */
    final public const ROLE_USER = 'ROLE_USER';
    final public const IS_AUTHENTICATED_FULLY = 'IS_AUTHENTICATED_FULLY';
    final public const ROLE_ADMIN = 'ROLE_ADMIN';

    private ?string $refreshToken = null;

    public function __construct(
        private readonly string $jwt,
        private readonly string $id,
        private readonly string $username,
        private readonly array $roles = [],
        private readonly array $groups = [],
        private readonly array $scopes = [],
    )
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getPassword()
    {
        return null;
    }

    public function getSalt()
    {
        return null;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->username;
    }

    public function eraseCredentials(): void
    {
    }

    public function getJwt(): string
    {
        return $this->jwt;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }
}
