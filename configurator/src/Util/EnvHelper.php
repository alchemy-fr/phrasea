<?php

namespace App\Util;

abstract readonly class EnvHelper
{
    public static function getEnvOrThrow(string $key): string
    {
        $value = getenv($key);
        if (false === $value) {
            throw new \RuntimeException(sprintf('Environment variable %s is not set.', $key));
        }

        if ('' === $value) {
            throw new \RuntimeException(sprintf('Environment variable %s is empty.', $key));
        }

        return $value;
    }

    public static function getEnv(string $key, ?string $defaultValue = null): ?string
    {
        $value = getenv($key);
        if (false === $value) {
            return $defaultValue;
        }

        return $value;
    }

    public static function getBooleanEnv(string $name, bool $defaultValue = false): bool
    {
        $val = filter_var(getenv($name), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if (null === $val) {
            return $defaultValue;
        }

        return $val;
    }
}
