<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Service\Import\Exception\InvalidImportEntryException;

/**
 * Checks the structure of an entry and keeps its known fields only (unknown ones are ignored,
 * for compatibility with future formats). The references (collection, tags, attribute and
 * rendition definitions) are resolved when the entry is imported.
 */
final class ImportEntryNormalizer
{
    private const array STRING_FIELDS = [
        'key',
        'externalId',
        'name',
        'collection',
        'path',
        'sourceName',
        'sourceType',
    ];

    private const array URL_FIELDS = [
        'sourceUrl',
        'previewUrl',
        'thumbnailUrl',
    ];

    /**
     * @return array{
     *     key?: string,
     *     externalId?: string,
     *     name?: string,
     *     collection?: string,
     *     path?: string[],
     *     sourceName?: string,
     *     sourceType?: string,
     *     sourceUrl?: string,
     *     previewUrl?: string,
     *     thumbnailUrl?: string,
     *     tags?: string[],
     *     privacy?: int|string,
     *     attributes?: array<string, scalar|list<scalar>>,
     *     renditions?: array<string, string>,
     * }
     *
     * @throws InvalidImportEntryException
     */
    public function normalize(array $entry): array
    {
        $normalized = [];

        foreach (self::STRING_FIELDS as $field) {
            if (null !== $value = self::getString($entry, $field)) {
                $normalized[$field] = $value;
            }
        }

        foreach (self::URL_FIELDS as $field) {
            if (null !== $value = self::getString($entry, $field)) {
                $normalized[$field] = self::checkUrl($value, $field);
            }
        }

        if (isset($normalized['collection'], $normalized['path'])) {
            throw new InvalidImportEntryException('"collection" and "path" cannot be used together');
        }
        if (isset($normalized['path'])) {
            $segments = array_values(array_filter(
                array_map(trim(...), explode('/', $normalized['path'])),
                static fn (string $segment): bool => '' !== $segment
            ));
            if (empty($segments)) {
                throw new InvalidImportEntryException('"path" must contain at least one collection name');
            }
            $normalized['path'] = $segments;
        }

        if (null !== $tags = $entry['tags'] ?? null) {
            if (!is_array($tags) || !array_is_list($tags)) {
                throw new InvalidImportEntryException('"tags" must be a list');
            }
            foreach ($tags as $tag) {
                if (!is_string($tag) || '' === trim($tag)) {
                    throw new InvalidImportEntryException('"tags" must be a list of tag IDs or names');
                }
            }
            $normalized['tags'] = array_values(array_unique(array_map(trim(...), $tags)));
        }

        if (null !== $privacy = $entry['privacy'] ?? null) {
            if (!is_int($privacy) && !(is_string($privacy) && '' !== $privacy)) {
                throw new InvalidImportEntryException('"privacy" must be an integer or a privacy label');
            }
            $normalized['privacy'] = $privacy;
        }

        if (null !== $attributes = $entry['attributes'] ?? null) {
            $normalized['attributes'] = self::normalizeAttributes($attributes);
        }

        if (null !== $renditions = $entry['renditions'] ?? null) {
            if (!is_array($renditions) || ([] !== $renditions && array_is_list($renditions))) {
                throw new InvalidImportEntryException('"renditions" must be an object of rendition URLs by definition');
            }
            foreach ($renditions as $definition => $url) {
                if (!is_string($url)) {
                    throw new InvalidImportEntryException(sprintf('Rendition "%s": the URL must be a string', $definition));
                }
                $normalized['renditions'][(string) $definition] = self::checkUrl($url, sprintf('renditions.%s', $definition));
            }
        }

        if (!isset($normalized['sourceUrl'])
            && !isset($normalized['previewUrl'])
            && !isset($normalized['thumbnailUrl'])
            && empty($normalized['renditions'])
            && !isset($normalized['name'])
        ) {
            throw new InvalidImportEntryException('The entry must have a "sourceUrl", a "previewUrl", a "thumbnailUrl", "renditions" or a "name"');
        }

        return $normalized;
    }

    private static function getString(array $entry, string $field): ?string
    {
        $value = $entry[$field] ?? null;
        if (null === $value) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            throw new InvalidImportEntryException(sprintf('"%s" must be a string', $field));
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }

    private static function checkUrl(string $url, string $field): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || false === filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidImportEntryException(sprintf('"%s" must be an HTTP(S) URL', $field));
        }

        return $url;
    }

    /**
     * @return array<string, scalar|list<scalar>>
     */
    private static function normalizeAttributes(mixed $attributes): array
    {
        if (!is_array($attributes) || ([] !== $attributes && array_is_list($attributes))) {
            throw new InvalidImportEntryException('"attributes" must be an object of values by attribute slug');
        }

        $normalized = [];
        foreach ($attributes as $name => $value) {
            $name = (string) $name;
            if (null === $value) {
                continue;
            }
            if (is_array($value)) {
                if (!array_is_list($value)) {
                    throw new InvalidImportEntryException(sprintf('Attribute "%s": the value must be a scalar or a list of scalars', $name));
                }
                foreach ($value as $item) {
                    if (!is_scalar($item)) {
                        throw new InvalidImportEntryException(sprintf('Attribute "%s": the value must be a scalar or a list of scalars', $name));
                    }
                }
            } elseif (!is_scalar($value)) {
                throw new InvalidImportEntryException(sprintf('Attribute "%s": the value must be a scalar or a list of scalars', $name));
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }
}
