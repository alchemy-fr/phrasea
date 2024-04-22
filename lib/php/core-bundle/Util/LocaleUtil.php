<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

final class LocaleUtil
{
    public static function normalizeLocale(string $locale): string
    {
        return trim(str_replace('-', '_', $locale));
    }

    public static function getBestLocale(array $availableLocales, array $locales): ?string
    {
        $availableLocales = array_map(self::normalizeLocale(...), $availableLocales);

        foreach ($locales as $l) {
            $l = self::normalizeLocale($l);

            if (in_array($l, $availableLocales, true)) {
                return $l;
            }

            if (str_contains($l, '_')) {
                [$_l] = explode('_', $l);

                if (in_array($_l, $availableLocales, true)) {
                    return $_l;
                }

                $l = $_l;
            }

            foreach ($availableLocales as $availableLocale) {
                [$_l] = explode('_', $availableLocale);

                if ($_l === $l) {
                    return $availableLocale;
                }
            }
        }

        return null;
    }

    public static function extractLanguageFromLocale(string $locale): string
    {
        return preg_replace('#_.+$#', '', $locale);
    }
}
