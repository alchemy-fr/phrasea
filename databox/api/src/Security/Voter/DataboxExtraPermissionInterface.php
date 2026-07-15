<?php

declare(strict_types=1);

namespace App\Security\Voter;

interface DataboxExtraPermissionInterface
{
    final public const int PERM_EDIT_PERMISSIONS = 1;
    final public const int PERM_MANAGE_USERS = 2;
    final public const int PERM_QUARANTINE = 3;
    final public const int PERM_QUARANTINE_BY_PASS = 4;
}
