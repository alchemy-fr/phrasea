<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

final readonly class RandomUtil
{
    public static function generateString(int $len): string
    {
        return bin2hex(random_bytes($len / 2));
    }

    public static function generateDigits(int $len): string
    {
        $s = '';
        for ($i = 0; $i < $len; ++$i) {
            $s .= random_int(0, 9);
        }

        return $s;
    }
}
