<?php

namespace Alchemy\ConfiguratorBundle;

final class StackConfig
{
    final public const string STACK_CONFIG_FILE_ENV_NAME = 'STACK_CONFIG_SRC';
    final public const string SRC = '/etc/app/stack-config.json';
    private static ?array $config;

    public static function getConfig(): array
    {
        if (!isset(self::$config)) {
            if (file_exists(self::SRC)) {
                self::$config = json_decode(file_get_contents(self::SRC), true, 512, JSON_THROW_ON_ERROR);
            } else {
                self::$config = [];
            }
        }

        return self::$config;
    }

    public static function generateConfigEnvKey(string $key, ?string $default = null): string
    {
        $keyParts = array_reverse(explode('.', $key));
        $prefix = implode('', array_map(fn (string $k): string => 'key:'.$k.':', $keyParts));

        if (null !== $default) {
            $prefix = 'default:'.str_replace('%', '%%', $default).':'.$prefix;
        }

        return '%env('.$prefix.'json:file:'.self::STACK_CONFIG_FILE_ENV_NAME.')%';
    }
}
