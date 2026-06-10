<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Util;

final readonly class LocaleUtil
{
    public static function normalizeLocale(string $locale): string
    {
        $locale = trim($locale);
        $locale = str_replace('-', '_', $locale);

        if (str_contains($locale, '_')) {
            [$lang, $region] = explode('_', $locale, 2);

            return strtolower($lang).'_'.strtoupper($region);
        }

        return strtolower($locale);
    }

    public static function getBestLocale(array $availableLocales, array $locales): ?string
    {
        $availableLocales = array_map(self::normalizeLocale(...), $availableLocales);

        foreach ($locales as $i => $l) {
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
            } else {
                $nextLocales = array_slice($locales, $i + 1);
                foreach ($nextLocales as $nextLocale) {
                    if (str_starts_with($nextLocale, $l.'_')) {
                        if (in_array($nextLocale, $availableLocales, true)) {
                            return $nextLocale;
                        }
                    }
                }
            }

            foreach ($availableLocales as $availableLocale) {
                if (str_starts_with($availableLocale, $l.'_')) {
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
