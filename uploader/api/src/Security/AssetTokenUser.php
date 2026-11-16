<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bearer of the token of a commit: reads its assets and acknowledges them, nothing else (no upload).
 */
final readonly class AssetTokenUser implements UserInterface
{
    final public const string ROLE = 'ROLE_ASSET_TOKEN';

    public function __construct()
    {
    }

    public function getRoles(): array
    {
        return [
            'ROLE_USER',
            self::ROLE,
        ];
    }

    public function getUserIdentifier(): string
    {
        return self::class;
    }
}
