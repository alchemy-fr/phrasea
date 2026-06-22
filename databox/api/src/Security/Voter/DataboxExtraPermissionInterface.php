<?php

declare(strict_types=1);

namespace App\Security\Voter;

interface DataboxExtraPermissionInterface
{
    final public const int PERM_EDIT_PERMISSIONS = 1;
    final public const int PERM_MANAGE_USERS = 2;
}
