<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Util\EnvHelper;

/**
 * Reads the CONFIGURATOR_CONFIGURE_<NAME> switches, disabled unless set to a true value.
 * Shared by the configurators and the Keycloak migrations so that both agree on the default.
 */
abstract readonly class ConfiguratorFlags
{
    public static function isEnabled(string $name): bool
    {
        return EnvHelper::getBooleanEnv('CONFIGURATOR_CONFIGURE_'.strtoupper($name));
    }
}
