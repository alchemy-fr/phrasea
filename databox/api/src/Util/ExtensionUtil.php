<?php

declare(strict_types=1);

namespace App\Util;

class ExtensionUtil
{
    public static function getExtension(string $path): string
    {
        $path = preg_replace('#\?.*$#', '', $path);

        return pathinfo($path, PATHINFO_EXTENSION) ?? '';
    }
}
