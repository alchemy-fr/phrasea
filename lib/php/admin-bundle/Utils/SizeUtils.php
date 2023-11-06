<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Utils;

class SizeUtils
{
    /**
     * @param int|float|null $size in bytes
     * @param bool           $si   True to use metric (SI) units, aka powers of 1000. False to use
     *                             binary (IEC), aka powers of 1024.
     */
    public static function formatSize($size, bool $si = true): ?string
    {
        $base = $si ? 1000 : 1024;
        if (null === $size) {
            return null;
        }

        if ($size < $base) {
            return number_format($size, 2, '.', ',').' B';
        }

        $units = $si ? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
            : ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];

        $u = -1;
        $r = 10 ** 2;
        $unitLength = count($units);
        do {
            $size /= $base;
            ++$u;
        } while (round($size * $r) / $r >= $base && $u < $unitLength - 1);

        return number_format($size, 2, '.', ',').' '.$units[$u];
    }
}
