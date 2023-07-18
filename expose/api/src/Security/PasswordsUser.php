<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class PasswordsUser implements UserInterface
{
    final public const USERNAME = 'passwords';

    public function __construct()
    {
    }

    public function getRoles(): array
    {
        return [];
    }

    public function eraseCredentials()
    {
    }

    public function getUserIdentifier(): string
    {
        return self::USERNAME;
    }
}
