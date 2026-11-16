<?php

declare(strict_types=1);

// The conversion container only gets src/RemoteExec and bin/: no Composer there
spl_autoload_register(static function (string $class): void {
    $prefix = 'Alchemy\\RenditionFactory\\RemoteExec\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/../src/RemoteExec/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
