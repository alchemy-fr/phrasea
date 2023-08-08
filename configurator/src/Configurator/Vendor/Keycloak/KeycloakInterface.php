<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Keycloak;

interface KeycloakInterface
{
    final public const GROUP_ADMIN = 'admin';
    final public const GROUP_SUPER_ADMIN = 'super-admin';
    final public const GROUP_TECH = 'tech';
    final public const GROUP_USER_ADMIN = 'user-admin';
    final public const GROUP_GROUP_ADMIN = 'group-admin';
}
