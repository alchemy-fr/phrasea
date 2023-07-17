<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AclBundle\Model\AclUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

if (interface_exists(AclUserInterface::class)) {
    interface JwtUserInterface extends AclUserInterface
    {
    }
} else {
    interface JwtUserInterface
    {
    }
}

class JwtUser implements UserInterface, JwtUserInterface
{
    const ROLE_USER = 'ROLE_USER';
    const ROLE_ADMIN = 'ROLE_ADMIN';

    private ?string $refreshToken = null;

    public function __construct(
        private readonly string $jwt,
        private readonly string $id,
        private readonly string $username,
        private readonly array $roles = [],
        private readonly array $groups = []
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

    public function getGroupIds(): array
    {
        return array_keys($this->groups);
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

    public function eraseCredentials()
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
}
