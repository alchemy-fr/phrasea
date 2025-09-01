<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

final readonly class LocaleUtil
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
                [$lang] = explode('_', $l);

                if (in_array($lang, $availableLocales, true)) {
                    return $lang;
                }

                $l = $lang;
            }

            foreach ($availableLocales as $availableLocale) {
                [$lang] = explode('_', $availableLocale);

                if ($lang === $l) {
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
