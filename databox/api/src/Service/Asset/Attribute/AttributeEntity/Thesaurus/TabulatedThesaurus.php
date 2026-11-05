<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Thesaurus;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Attribute\AttributeInterface;
use App\Entity\Core\AttributeEntity;

/**
 * The tabulated thesaurus format: one entity per line, indented under its parent (no single root).
 *
 *     animal
 *         mammifère [fr]; mammal [en]
 *             {#ff0000} chien [fr] ; dog [en]
 *             ! {#00F800} cat [en]; chat [fr]
 *         oiseau [fr]
 *             ? pigeon
 *
 * - A leading "?" makes the entity pending, "!" rejected; approved otherwise.
 * - "{#rrggbb}" is its optional color.
 * - The terms are separated by ";"; "term [locale]" is a translation, the next terms in the same locale its
 *   synonyms. The value is the first term without locale, the first term otherwise; the next terms without
 *   locale are synonyms.
 * - Given a default locale, the terms without locale are in that locale: the first one is also its translation
 *   (unless a term of that locale comes before), the next ones its synonyms in that locale.
 *
 * Several colors on a line are a syntax error.
 */
final class TabulatedThesaurus
{
    public const string FORMAT = 'thesaurus';

    private const string PENDING = '?';
    private const string REJECTED = '!';
    private const string COLOR_PATTERN = '/\{\s*(#[^}]*?)\s*\}/';
    private const string VALID_COLOR = '/^#[0-9a-f]{6}$/i';
    private const string TRANSLATION_PATTERN = '/^(.*?)\s*\[\s*([a-z]{2,3}(?:[_-][a-z0-9]{2,8})*)\s*\]$/i';

    /**
     * @return list<TabulatedThesaurusLine> in the order of the lines, parents first
     *
     * @throws TabulatedThesaurusSyntaxException
     */
    public static function parse(string $data, ?string $defaultLocale = null): array
    {
        $defaultLocale = null !== $defaultLocale && '' !== trim($defaultLocale) ? LocaleUtil::normalizeLocale($defaultLocale) : null;
        $errors = [];
        $parsed = [];
        // The ancestors of the next line: [indentation, index in $parsed]
        $stack = [];

        foreach (preg_split('/\r\n|\n|\r/', $data) ?: [] as $i => $raw) {
            $line = $i + 1;
            if ('' === trim($raw)) {
                continue;
            }

            preg_match('/^[ \t]*/', $raw, $m);
            $indent = $m[0];
            $popped = null;
            while (!empty($stack) && !self::isDeeper($indent, $stack[\count($stack) - 1][0])) {
                $popped = array_pop($stack);
            }
            if (null !== $popped ? $popped[0] !== $indent : (empty($stack) && '' !== $indent)) {
                $errors[] = ['line' => $line, 'message' => 'The indentation does not match any upper level'];
            }
            $parent = empty($stack) ? null : $stack[\count($stack) - 1][1];

            $entity = self::parseContent(trim($raw), $line, $parent, $defaultLocale, $errors);
            if (null !== $entity) {
                $stack[] = [$indent, \count($parsed)];
                $parsed[] = $entity;
            }
        }

        if (!empty($errors)) {
            throw new TabulatedThesaurusSyntaxException($errors);
        }

        return $parsed;
    }

    /**
     * @param array<string, string>       $translations
     * @param array<string, list<string>> $synonyms     locale (NO_LOCALE for the synonyms of the value) => terms
     */
    public static function formatLine(int $depth, int $status, ?string $color, string $value, array $translations, array $synonyms): string
    {
        $valueSynonyms = $synonyms[AttributeInterface::NO_LOCALE] ?? [];
        unset($synonyms[AttributeInterface::NO_LOCALE]);

        $prefix = match ($status) {
            AttributeEntity::STATUS_PENDING => self::PENDING.' ',
            AttributeEntity::STATUS_REJECTED => self::REJECTED.' ',
            default => '',
        };
        if (null !== $color && '' !== $color) {
            $prefix .= sprintf('{%s} ', $color);
        }

        $terms = [];
        $valueLocale = empty($valueSynonyms) ? array_search($value, $translations, true) : false;
        if (false !== $valueLocale) {
            // Read back as the value: the first term, without any term without locale
            $terms[] = sprintf('%s [%s]', $value, $valueLocale);
        } else {
            $terms[] = $value;
            array_push($terms, ...$valueSynonyms);
        }
        // A translation is followed by its synonyms in its locale; a synonym without translation is read back as one
        foreach (array_keys($translations + $synonyms) as $locale) {
            $localeTerms = array_merge(isset($translations[$locale]) ? [$translations[$locale]] : [], $synonyms[$locale] ?? []);
            if ($locale === $valueLocale) {
                array_shift($localeTerms);
            }
            foreach ($localeTerms as $term) {
                $terms[] = sprintf('%s [%s]', $term, $locale);
            }
        }

        return str_repeat("\t", $depth).$prefix.implode('; ', $terms);
    }

    /**
     * @param list<array{line: int, message: string}> $errors
     */
    private static function parseContent(string $content, int $line, ?int $parent, ?string $defaultLocale, array &$errors): ?TabulatedThesaurusLine
    {
        $status = AttributeEntity::STATUS_APPROVED;
        if (str_starts_with($content, self::PENDING)) {
            $status = AttributeEntity::STATUS_PENDING;
        } elseif (str_starts_with($content, self::REJECTED)) {
            $status = AttributeEntity::STATUS_REJECTED;
        }
        if (AttributeEntity::STATUS_APPROVED !== $status) {
            $content = ltrim(substr($content, 1));
        }

        $color = null;
        preg_match_all(self::COLOR_PATTERN, $content, $colors);
        if (\count($colors[1]) > 1) {
            $errors[] = ['line' => $line, 'message' => 'Several colors'];
        } elseif (1 === \count($colors[1])) {
            $color = strtolower($colors[1][0]);
            if (!preg_match(self::VALID_COLOR, $color)) {
                $errors[] = ['line' => $line, 'message' => sprintf('Invalid color "%s" (expected #rrggbb)', $colors[1][0])];
            }
        }
        $content = (string) preg_replace(self::COLOR_PATTERN, '', $content);

        $untranslated = [];
        $translations = [];
        $synonyms = [];
        $first = null;
        foreach (explode(';', $content) as $term) {
            $term = trim($term);
            if ('' === $term) {
                continue;
            }
            if (preg_match(self::TRANSLATION_PATTERN, $term, $m)) {
                [, $text, $locale] = $m;
                $locale = LocaleUtil::normalizeLocale($locale);
            } else {
                $untranslated[] = $term;
                $first ??= $term;
                if (1 === \count($untranslated) && null !== $defaultLocale) {
                    // The first term without locale is a term in the default locale
                    [$text, $locale] = [$term, $defaultLocale];
                } else {
                    // The value itself, or a synonym without locale
                    if (\count($untranslated) > 1) {
                        self::addSynonym($synonyms, $translations, $defaultLocale ?? AttributeInterface::NO_LOCALE, $term);
                    }
                    continue;
                }
            }

            if ('' === $text) {
                $errors[] = ['line' => $line, 'message' => sprintf('Empty term in [%s]', $locale)];
                continue;
            }
            if (isset($translations[$locale])) {
                self::addSynonym($synonyms, $translations, $locale, $text);
            } else {
                $translations[$locale] = $text;
            }
            $first ??= $text;
        }

        if (null === $first) {
            $errors[] = ['line' => $line, 'message' => 'No term'];

            return null;
        }

        return new TabulatedThesaurusLine(
            line: $line,
            parent: $parent,
            value: $untranslated[0] ?? $first,
            status: $status,
            color: $color,
            translations: $translations,
            synonyms: $synonyms,
        );
    }

    /**
     * @param array<string, list<string>> $synonyms
     * @param array<string, string>       $translations
     */
    private static function addSynonym(array &$synonyms, array $translations, string $locale, string $term): void
    {
        if (($translations[$locale] ?? null) !== $term && !\in_array($term, $synonyms[$locale] ?? [], true)) {
            $synonyms[$locale][] = $term;
        }
    }

    /**
     * Whether an indentation is under another one: it extends it.
     */
    private static function isDeeper(string $indent, string $than): bool
    {
        return \strlen($indent) > \strlen($than) && str_starts_with($indent, $than);
    }
}
