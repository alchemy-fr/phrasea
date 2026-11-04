<?php

declare(strict_types=1);

namespace App\Service\Admin;

/**
 * Validates and normalizes the organisation theme submitted by an
 * administrator: a light palette, an optional dark alternative and a few
 * style properties. The values end up as CSS custom properties in every
 * user's browser, so the shape is a strict allow-list: known color tokens as
 * hex colors, bounded numbers, and a font family restricted to a safe charset.
 */
final class ClientThemeNormalizer
{
    final public const array COLOR_TOKENS = [
        'background',
        'foreground',
        'card',
        'card-foreground',
        'popover',
        'popover-foreground',
        'primary',
        'primary-foreground',
        'secondary',
        'secondary-foreground',
        'muted',
        'muted-foreground',
        'accent',
        'accent-foreground',
        'destructive',
        'destructive-foreground',
        'warning',
        'warning-foreground',
        'success',
        'success-foreground',
        'border',
        'input',
        'ring',
        'sidebar',
        'sidebar-foreground',
        'media-bg',
    ];

    final public const int NAME_MAX_LENGTH = 50;
    final public const float RADIUS_MIN = 0.0;
    final public const float RADIUS_MAX = 2.0;
    final public const int FONT_SIZE_MIN = 11;
    final public const int FONT_SIZE_MAX = 20;
    final public const int FONT_FAMILY_MAX_LENGTH = 120;
    final public const float LETTER_SPACING_MIN = -0.05;
    final public const float LETTER_SPACING_MAX = 0.1;

    /**
     * Fonts uploaded by the administrator, kept in the entry as data URIs
     * (like the logo of the global configuration). They travel in
     * `config.json`, which every page of every client reads, so they are few
     * and small: a subsetted woff2 is an order of magnitude below the limit.
     */
    final public const int FONTS_MAX = 4;
    final public const int FONT_NAME_MAX_LENGTH = 50;
    final public const int FONT_SRC_MAX_LENGTH = 300000;
    final public const array FONT_WEIGHTS = ['100', '200', '300', 'normal', '500', '600', 'bold', '800', '900'];
    final public const array FONT_STYLES = ['normal', 'italic'];

    private const array KEYS = ['name', 'default', 'colors', 'dark', 'radius', 'fontSize', 'fontFamily', 'letterSpacing', 'fonts'];

    private const array FONT_KEYS = ['family', 'src', 'weight', 'style'];

    /**
     * @throws InvalidClientThemeException
     */
    public function normalize(mixed $input): array
    {
        $violations = [];
        if (!is_array($input) || array_is_list($input)) {
            throw new InvalidClientThemeException([['propertyPath' => '', 'message' => 'The theme must be an object.']]);
        }

        foreach (array_keys($input) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                $violations[] = ['propertyPath' => (string) $key, 'message' => 'Unknown property.'];
            }
        }

        $name = $input['name'] ?? null;
        if (!is_string($name) || '' === trim($name)) {
            $violations[] = ['propertyPath' => 'name', 'message' => 'The name is required.'];
        } elseif (mb_strlen(trim($name)) > self::NAME_MAX_LENGTH) {
            $violations[] = ['propertyPath' => 'name', 'message' => sprintf('The name must be at most %d characters long.', self::NAME_MAX_LENGTH)];
        }

        $default = $input['default'] ?? false;
        if (!is_bool($default)) {
            $violations[] = ['propertyPath' => 'default', 'message' => 'Must be a boolean.'];
        }

        $colors = $this->normalizeColors($input['colors'] ?? [], 'colors', $violations);

        $dark = null;
        if (isset($input['dark'])) {
            $dark = $this->normalizeColors($input['dark'], 'dark', $violations);
        }

        $radius = $input['radius'] ?? null;
        if (null !== $radius) {
            if (!is_int($radius) && !is_float($radius)) {
                $violations[] = ['propertyPath' => 'radius', 'message' => 'Must be a number (rem).'];
            } elseif ($radius < self::RADIUS_MIN || $radius > self::RADIUS_MAX) {
                $violations[] = ['propertyPath' => 'radius', 'message' => sprintf('Must be between %s and %s.', self::RADIUS_MIN, self::RADIUS_MAX)];
            } else {
                $radius = round((float) $radius, 3);
            }
        }

        $fontSize = $input['fontSize'] ?? null;
        if (null !== $fontSize) {
            if (!is_int($fontSize) && !(is_float($fontSize) && floor($fontSize) === $fontSize)) {
                $violations[] = ['propertyPath' => 'fontSize', 'message' => 'Must be an integer (px).'];
            } elseif ($fontSize < self::FONT_SIZE_MIN || $fontSize > self::FONT_SIZE_MAX) {
                $violations[] = ['propertyPath' => 'fontSize', 'message' => sprintf('Must be between %d and %d.', self::FONT_SIZE_MIN, self::FONT_SIZE_MAX)];
            } else {
                $fontSize = (int) $fontSize;
            }
        }

        $letterSpacing = $input['letterSpacing'] ?? null;
        if (null !== $letterSpacing) {
            if (!is_int($letterSpacing) && !is_float($letterSpacing)) {
                $violations[] = ['propertyPath' => 'letterSpacing', 'message' => 'Must be a number (em).'];
            } elseif ($letterSpacing < self::LETTER_SPACING_MIN || $letterSpacing > self::LETTER_SPACING_MAX) {
                $violations[] = ['propertyPath' => 'letterSpacing', 'message' => sprintf('Must be between %s and %s.', self::LETTER_SPACING_MIN, self::LETTER_SPACING_MAX)];
            } else {
                $letterSpacing = round((float) $letterSpacing, 3);
            }
        }

        $fonts = null;
        if (isset($input['fonts'])) {
            $fonts = $this->normalizeFonts($input['fonts'], $violations);
        }

        $fontFamily = $input['fontFamily'] ?? null;
        if (null !== $fontFamily) {
            if (!is_string($fontFamily)) {
                $violations[] = ['propertyPath' => 'fontFamily', 'message' => 'Must be a string.'];
            } else {
                $fontFamily = trim($fontFamily);
                if ('' === $fontFamily) {
                    $fontFamily = null;
                } elseif (mb_strlen($fontFamily) > self::FONT_FAMILY_MAX_LENGTH || !preg_match('/^[a-zA-Z0-9 ,\'"_-]+$/', $fontFamily)) {
                    $violations[] = ['propertyPath' => 'fontFamily', 'message' => 'Must be a list of font names (letters, digits, spaces, commas, quotes and dashes).'];
                }
            }
        }

        if (!empty($violations)) {
            throw new InvalidClientThemeException($violations);
        }

        $theme = [
            'name' => trim($name),
            'default' => $default,
            'colors' => $colors,
        ];
        if (null !== $dark) {
            $theme['dark'] = $dark;
        }
        if (null !== $radius) {
            $theme['radius'] = $radius;
        }
        if (null !== $fontSize) {
            $theme['fontSize'] = $fontSize;
        }
        if (null !== $fontFamily) {
            $theme['fontFamily'] = $fontFamily;
        }
        if (null !== $letterSpacing) {
            $theme['letterSpacing'] = $letterSpacing;
        }
        if (!empty($fonts)) {
            $theme['fonts'] = $fonts;
        }

        return $theme;
    }

    /**
     * @param list<array{propertyPath: string, message: string}> $violations
     *
     * @return list<array{family: string, src: string, weight?: string, style?: string}>
     */
    private function normalizeFonts(mixed $input, array &$violations): array
    {
        if (!is_array($input) || !array_is_list($input)) {
            $violations[] = ['propertyPath' => 'fonts', 'message' => 'Must be a list of fonts.'];

            return [];
        }
        if (count($input) > self::FONTS_MAX) {
            $violations[] = ['propertyPath' => 'fonts', 'message' => sprintf('At most %d fonts can be uploaded.', self::FONTS_MAX)];

            return [];
        }

        $fonts = [];
        foreach ($input as $index => $raw) {
            $path = 'fonts['.$index.']';
            if (!is_array($raw) || array_is_list($raw)) {
                $violations[] = ['propertyPath' => $path, 'message' => 'Must be an object.'];
                continue;
            }
            foreach (array_keys($raw) as $key) {
                if (!in_array($key, self::FONT_KEYS, true)) {
                    $violations[] = ['propertyPath' => $path.'.'.$key, 'message' => 'Unknown property.'];
                }
            }

            $family = $raw['family'] ?? null;
            $family = is_string($family) ? trim($family) : '';
            if ('' === $family || mb_strlen($family) > self::FONT_NAME_MAX_LENGTH || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9 _-]*$/', $family)) {
                $violations[] = ['propertyPath' => $path.'.family', 'message' => 'Must be a font name (letters, digits, spaces, underscores and dashes).'];
                continue;
            }

            $src = $raw['src'] ?? null;
            if (!is_string($src) || !preg_match('#^data:font/(woff2|woff|ttf|otf);base64,[A-Za-z0-9+/]+={0,2}$#', $src)) {
                $violations[] = ['propertyPath' => $path.'.src', 'message' => 'Must be a data URI of a woff2, woff, ttf or otf file.'];
                continue;
            }
            if (strlen($src) > self::FONT_SRC_MAX_LENGTH) {
                $violations[] = ['propertyPath' => $path.'.src', 'message' => sprintf('The file is too large (at most %d characters once encoded).', self::FONT_SRC_MAX_LENGTH)];
                continue;
            }

            $font = ['family' => $family, 'src' => $src];

            if (isset($raw['weight'])) {
                $weight = is_scalar($raw['weight']) ? (string) $raw['weight'] : '';
                if (!in_array($weight, self::FONT_WEIGHTS, true)) {
                    $violations[] = ['propertyPath' => $path.'.weight', 'message' => sprintf('Must be one of: %s.', implode(', ', self::FONT_WEIGHTS))];
                    continue;
                }
                $font['weight'] = $weight;
            }

            if (isset($raw['style'])) {
                if (!is_string($raw['style']) || !in_array($raw['style'], self::FONT_STYLES, true)) {
                    $violations[] = ['propertyPath' => $path.'.style', 'message' => sprintf('Must be one of: %s.', implode(', ', self::FONT_STYLES))];
                    continue;
                }
                $font['style'] = $raw['style'];
            }

            $fonts[] = $font;
        }

        return $fonts;
    }

    /**
     * @param list<array{propertyPath: string, message: string}> $violations
     */
    private function normalizeColors(mixed $input, string $path, array &$violations): array
    {
        $colors = [];
        if (!is_array($input) || (!empty($input) && array_is_list($input))) {
            $violations[] = ['propertyPath' => $path, 'message' => 'Must be an object of color tokens.'];

            return $colors;
        }
        foreach ($input as $token => $value) {
            if (!in_array($token, self::COLOR_TOKENS, true)) {
                $violations[] = ['propertyPath' => $path.'.'.$token, 'message' => 'Unknown color token.'];
                continue;
            }
            if (null === $value || '' === $value) {
                continue;
            }
            if (!is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $violations[] = ['propertyPath' => $path.'.'.$token, 'message' => 'Must be a hex color (#rrggbb).'];
                continue;
            }
            $colors[$token] = strtolower($value);
        }

        return $colors;
    }
}
