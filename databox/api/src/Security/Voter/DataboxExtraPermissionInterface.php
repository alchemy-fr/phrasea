<?php

declare(strict_types=1);

namespace App\Security\Voter;

interface DataboxExtraPermissionInterface
{
    final public const int PERM_EDIT_PERMISSIONS = 1;
    final public const int PERM_MANAGE_USERS = 2;
    final public const int PERM_QUARANTINE = 3;
    final public const int PERM_QUARANTINE_BY_PASS = 4;

    /*
     * Instance capabilities (App\Security\InstancePermission), set on the ACEs of a
     * whole object type. Metadata checks on an object also read the ACEs of its type:
     * these values must differ from the ones above.
     */
    final public const int PERM_CREATE_WORKSPACE = 5;
    final public const int PERM_CREATE_PROFILE = 6;
    final public const int PERM_SHARE_BASKET = 7;
    final public const int PERM_PUBLISH_SAVED_SEARCH = 8;
}
