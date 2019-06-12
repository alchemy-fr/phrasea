<?php

declare(strict_types=1);

namespace App\Model;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    const ADMIN_USER = 'admin@alchemy.fr';

    /**
     * @var string
     */
    private $username;
    /**
     * @var string
     */
    private $id;

    public function __construct(string $id, string $username)
    {
        $this->username = $username;
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRoles()
    {
        $roles = ['ROLE_USER'];

        // TODO tmp: should be provided by Identity Provider
        if (self::ADMIN_USER === $this->username) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        return $roles;
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

    public function eraseCredentials()
    {
    }
}
