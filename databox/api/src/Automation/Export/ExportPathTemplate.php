<?php

declare(strict_types=1);

namespace App\Automation\Export;

/**
 * The path of an exported file on the destination, from a template such as
 * "{collection}/{assetId}-{rendition}.{ext}".
 *
 * Every value is reduced to a single path segment, so that only the template
 * decides of the directories.
 *
 * The variables default to those of an exported asset file (VARIABLES); the report
 * file of a run uses REPORT_VARIABLES.
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

    final public const string REPORT_DEFAULT = '{savedSearch}/{automation}-{datetime}.xlsx';

    final public const array REPORT_VARIABLES = [
        'automation',
        'savedSearch',
        'date',
        'datetime',
        'runId',
    ];

    private const int MAX_LENGTH = 1000;

    /**
     * @param string[] $variables the variables the template may use
     *
     * @return string|null the error, if any
     */
    public static function validate(string $template, array $variables = self::VARIABLES): ?string
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
        $unknown = array_diff($matches[1], $variables);
        if (!empty($unknown)) {
            return sprintf('Unknown variable "{%s}". Available variables: %s.', reset($unknown), implode(', ', array_map(fn (string $v): string => '{'.$v.'}', $variables)));
        }

        return null;
    }

    /**
     * @param string[] $variables the variables the template may use
     *
     * @return string[] the variables the template uses
     */
    public static function getUsedVariables(string $template, array $variables = self::VARIABLES): array
    {
        preg_match_all('/{([^}]*)}/', $template, $matches);

        return array_values(array_unique(array_intersect($matches[1], $variables)));
    }

    /**
     * @param array<string, string|null> $values    indexed by variable name
     * @param string[]                   $variables the variables the template may use
     */
    public static function render(string $template, array $values, array $variables = self::VARIABLES): string
    {
        // A missing extension drops its dot instead of leaving the name ending with "."
        if (in_array('ext', $variables, true) && '' === self::sanitizeSegment($values['ext'] ?? null)) {
            $template = str_replace('.{ext}', '', $template);
        }

        $path = preg_replace_callback(
            '/{([^}]*)}/',
            fn (array $m): string => in_array($m[1], $variables, true) ? self::sanitizeSegment($values[$m[1]] ?? null) : $m[0],
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
