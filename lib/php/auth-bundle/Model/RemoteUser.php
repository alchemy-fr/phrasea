<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Model;

use Alchemy\AclBundle\Model\AclUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

if (interface_exists(AclUserInterface::class)) {
    interface RemoteUserInterface extends AclUserInterface
    {
    }
} else {
    interface RemoteUserInterface
    {
    }
}

class RemoteUser implements UserInterface, RemoteUserInterface
{
    const ROLE_USER = 'ROLE_USER';
    const ROLE_ADMIN = 'ROLE_ADMIN';

    public function __construct(private readonly string $id, private readonly string $username, private readonly array $roles = [], private readonly array $groups = [])
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
}
