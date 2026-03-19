<?php

namespace App\Security\Voter;

interface DataboxExtraPermissionInterface
{
    final public const int PERM_EDIT_PERMISSIONS = 1;
    final public const int PERM_EDIT_TAG = 2;
    final public const int PERM_MANAGE_USERS = 3;
}
