<?php

declare(strict_types=1);

namespace App\Util;

class LocaleUtils
{
    public static function extractLanguageFromLocale(string $locale): string
    {
        return preg_replace('#_.+$#', '', $locale);
    }
}
