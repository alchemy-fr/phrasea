<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Keycloak;

interface KeycloakInterface
{
    final public const ROLE_ADMIN = 'admin';
    final public const ROLE_TECH = 'tech';
    final public const ROLE_USER_ADMIN = 'user-admin';
    final public const ROLE_GROUP_ADMIN = 'group-admin';
}
