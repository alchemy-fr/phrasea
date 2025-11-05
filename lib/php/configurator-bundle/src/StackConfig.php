<?php

namespace Alchemy\ConfiguratorBundle;

final class StackConfig
{
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
}
