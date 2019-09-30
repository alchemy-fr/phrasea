<?php

declare(strict_types=1);

namespace App\Security\Voter;

abstract class ClientAuthorizations
{
    const READ_USERS = 'READ_USERS';

    public static function getList(): array
    {
        return [
            self::READ_USERS,
        ];
    }
}
