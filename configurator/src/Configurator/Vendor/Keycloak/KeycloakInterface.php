<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Keycloak;

interface KeycloakInterface
{
    final public const string ROLE_ADMIN = 'admin';
    final public const string ROLE_TECH = 'tech';
    final public const string ROLE_USER_ADMIN = 'user-admin';
    final public const string ROLE_GROUP_ADMIN = 'group-admin';
}
