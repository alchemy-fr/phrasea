<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Keycloak;

interface KeycloakInterface
{
    final public const string ROLE_ADMIN = 'admin';
    final public const string ROLE_TECH = 'tech';
    final public const string ROLE_USER_ADMIN = 'user-admin';
    final public const string ROLE_GROUP_ADMIN = 'group-admin';

    final public const string REALM_MANAGEMENT_CLIENT_ID = 'realm-management';
    /**
     * Roles of the "realm-management" client composing ROLE_GROUP_ADMIN: the applications let the
     * users holding "manage-users" manage the groups (and Keycloak lets them, with their own token).
     */
    final public const array GROUP_ADMIN_CLIENT_ROLES = ['view-users', 'manage-users'];
}
