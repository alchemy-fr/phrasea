<?php

declare(strict_types=1);

namespace App\Automation\Export;

/**
 * The path of an exported file on the destination, from a template such as
 * "{collection}/{assetId}-{rendition}.{ext}".
 *
 * Every value is reduced to a single path segment, so that only the template
 * decides of the directories.
 */
final class ExportPathTemplate
{
    final public const string DEFAULT = '{collection}/{assetId}-{rendition}.{ext}';

    final public const array VARIABLES = [
        'assetId',
        'title',
        'rendition',
        'ext',
        'originalName',
        'workspace',
        'collection',
        'date',
    ];

    private const int MAX_LENGTH = 1000;

    /**
     * @return string|null the error, if any
     */
    public static function validate(string $template): ?string
    {
        if ('' === trim($template, " \n\r\t\v\0/")) {
            return 'This value should not be blank.';
        }
        if (mb_strlen($template) > self::MAX_LENGTH) {
            return sprintf('This value is too long (%d characters at most).', self::MAX_LENGTH);
        }
        if (str_contains($template, '..')) {
            return 'The path cannot contain "..".';
        }
        if (str_contains($template, '\\') || preg_match('/[\x00-\x1F\x7F]/', $template)) {
            return 'The path contains forbidden characters.';
        }

        preg_match_all('/{([^}]*)}/', $template, $matches);
        $unknown = array_diff($matches[1], self::VARIABLES);
        if (!empty($unknown)) {
            return sprintf('Unknown variable "{%s}". Available variables: %s.', reset($unknown), implode(', ', array_map(fn (string $v): string => '{'.$v.'}', self::VARIABLES)));
        }

        return null;
    }

    /**
     * @return string[] the variables the template uses
     */
    public static function getUsedVariables(string $template): array
    {
        preg_match_all('/{([^}]*)}/', $template, $matches);

        return array_values(array_unique(array_intersect($matches[1], self::VARIABLES)));
    }

    /**
     * @param array<string, string|null> $values indexed by variable name
     */
    public static function render(string $template, array $values): string
    {
        // A missing extension drops its dot instead of leaving the name ending with "."
        if ('' === self::sanitizeSegment($values['ext'] ?? null)) {
            $template = str_replace('.{ext}', '', $template);
        }

        $path = preg_replace_callback(
            '/{([^}]*)}/',
            fn (array $m): string => in_array($m[1], self::VARIABLES, true) ? self::sanitizeSegment($values[$m[1]] ?? null) : $m[0],
            $template,
        );

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $segment = trim($segment);
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                throw new \InvalidArgumentException('The export path cannot contain ".."');
            }
            $segments[] = $segment;
        }

        if (empty($segments)) {
            throw new \InvalidArgumentException(sprintf('The export path template "%s" resolves to an empty path', $template));
        }

        return implode('/', $segments);
    }

    private static function sanitizeSegment(?string $value): string
    {
        if (null === $value) {
            return '';
        }

        $value = trim(preg_replace('#[/\\\\\x00-\x1F\x7F]+#', '-', $value));

        return '.' === $value || '..' === $value ? '' : $value;
    }
}
