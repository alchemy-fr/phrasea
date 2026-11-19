<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Database;

use App\Util\EnvHelper;

/**
 * The PostgreSQL role an application connects with instead of the superuser.
 */
final readonly class DatabaseRole
{
    public function __construct(
        public string $name,
        public string $password,
    ) {
        if ('' === $name || '' === $password) {
            throw new \InvalidArgumentException('A database role needs a name and a password.');
        }
    }

    /**
     * Reads {PREFIX}_POSTGRES_USER and {PREFIX}_POSTGRES_PASSWORD: null when both are empty,
     * since the application then keeps connecting with the superuser.
     */
    public static function fromEnv(string $prefix): ?self
    {
        $userVar = $prefix.'_POSTGRES_USER';
        $passwordVar = $prefix.'_POSTGRES_PASSWORD';
        $user = EnvHelper::getEnv($userVar) ?? '';
        $password = EnvHelper::getEnv($passwordVar) ?? '';

        if ('' === $user && '' === $password) {
            return null;
        }

        if ('' === $user || '' === $password) {
            throw new \InvalidArgumentException(sprintf('%s and %s must be both set or both empty.', $userVar, $passwordVar));
        }

        return new self($user, $password);
    }
}
