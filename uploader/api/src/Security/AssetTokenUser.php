<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class AssetTokenUser implements UserInterface
{
    public function __construct()
    {
    }

    public function getRoles(): array
    {
        return [
            'ROLE_USER',
        ];
    }

    public function eraseCredentials()
    {
    }

    public function getUserIdentifier(): string
    {
        return self::class;
    }
}
