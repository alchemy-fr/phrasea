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

    /**
     * @var array
     */
    private $roles;

    public function __construct(string $id, string $username, array $roles = null)
    {
        $this->username = $username;
        $this->id = $id;
        $this->roles = $roles;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRoles()
    {
        return $this->roles;
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
