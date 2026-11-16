<?php

declare(strict_types=1);

namespace App\Attribute;

/**
 * The locales that may become an "attributes.<locale>" object of the asset index, whose mapping
 * is shared by every workspace: "_" (untranslated values), the locales of
 * AttributeLocaleInterface::LOCALES (those IndexMappingTemplatesMaker maps) and the ones
 * enabled by the workspace. Any other key would let a user add fields to that mapping at will.
 */
final readonly class IndexableLocales
{
    /**
     * @param string[] $workspaceLocales
     */
    public static function isIndexable(string $locale, array $workspaceLocales): bool
    {
        return AttributeInterface::NO_LOCALE === $locale
            || self::isKnown($locale, $workspaceLocales);
    }

    /**
     * Like isIndexable(), "_" excluded.
     *
     * @param string[] $workspaceLocales
     */
    public static function isKnown(string $locale, array $workspaceLocales): bool
    {
        return isset(AttributeLocaleInterface::LOCALES[$locale])
            || in_array($locale, $workspaceLocales, true);
    }

    /**
     * @template T
     *
     * @param array<array-key, T> $values           keyed by locale
     * @param string[]            $workspaceLocales
     *
     * @return array<string, T>
     */
    public static function filter(array $values, array $workspaceLocales): array
    {
        return array_filter(
            $values,
            static fn (int|string $locale): bool => self::isIndexable((string) $locale, $workspaceLocales),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
