<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Admin;

use App\Service\Admin\ClientThemeNormalizer;
use App\Service\Admin\InvalidClientThemeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The allow-list applied to the organisation theme before it is stored (its
 * values end up as CSS in every user's browser). The API wiring is covered by
 * App\Tests\Functional\Api\ClientThemeTest.
 */
class ClientThemeNormalizerTest extends TestCase
{
    /** A font file is stored in the entry as a data URI, like the logo */
    private const string WOFF2 = 'data:font/woff2;base64,d09GMgABAAAAAA==';

    public function testNormalizesAValidTheme(): void
    {
        $theme = (new ClientThemeNormalizer())->normalize([
            'name' => '  Acme  ',
            'default' => true,
            'colors' => [
                'primary' => '#FF6600',
                'primary-foreground' => '#ffffff',
                'background' => '',
                'card' => null,
            ],
            'dark' => [
                'primary' => '#FF8A3D',
            ],
            'radius' => 0.75,
            'fontSize' => 15.0,
            'fontFamily' => '  Inter, "Helvetica Neue", sans-serif ',
            'letterSpacing' => 0.01,
            'fonts' => [
                ['family' => ' Acme Sans ', 'src' => self::WOFF2, 'weight' => 'bold', 'style' => 'italic'],
            ],
        ]);

        self::assertSame([
            'name' => 'Acme',
            'default' => true,
            'colors' => [
                'primary' => '#ff6600',
                'primary-foreground' => '#ffffff',
            ],
            'dark' => [
                'primary' => '#ff8a3d',
            ],
            'radius' => 0.75,
            'fontSize' => 15,
            'fontFamily' => 'Inter, "Helvetica Neue", sans-serif',
            'letterSpacing' => 0.01,
            'fonts' => [
                ['family' => 'Acme Sans', 'src' => self::WOFF2, 'weight' => 'bold', 'style' => 'italic'],
            ],
        ], $theme);
    }

    public function testOptionalPropertiesAreOmitted(): void
    {
        self::assertSame([
            'name' => 'Acme',
            'default' => false,
            'colors' => [],
        ], (new ClientThemeNormalizer())->normalize([
            'name' => 'Acme',
            'fontFamily' => '   ',
            'fonts' => [],
        ]));
    }

    public function testNumbersAreRounded(): void
    {
        $theme = (new ClientThemeNormalizer())->normalize([
            'name' => 'Acme',
            'radius' => 1,
            'letterSpacing' => 0.012345,
        ]);

        self::assertSame(1.0, $theme['radius']);
        self::assertSame(0.012, $theme['letterSpacing']);
    }

    public function testEveryViolationIsReported(): void
    {
        try {
            (new ClientThemeNormalizer())->normalize([
                'name' => '',
                'colors' => ['primary' => 'red'],
                'radius' => 5,
            ]);
            self::fail('An invalid theme must be rejected');
        } catch (InvalidClientThemeException $e) {
            self::assertSame(['name', 'colors.primary', 'radius'], array_column($e->getViolations(), 'propertyPath'));
        }
    }

    #[DataProvider('invalidThemeProvider')]
    public function testRejectsInvalidThemes(array $theme, string $propertyPath): void
    {
        try {
            (new ClientThemeNormalizer())->normalize($theme);
            self::fail('An invalid theme must be rejected');
        } catch (InvalidClientThemeException $e) {
            $paths = array_column($e->getViolations(), 'propertyPath');
            self::assertContains($propertyPath, $paths, sprintf('Expected a violation on "%s", got: %s', $propertyPath, implode(', ', $paths)));
        }
    }

    public static function invalidThemeProvider(): iterable
    {
        yield 'not an object' => [['#000000'], ''];
        yield 'missing name' => [['mode' => 'light'], 'name'];
        yield 'blank name' => [['name' => '   '], 'name'];
        yield 'name too long' => [['name' => str_repeat('a', 51)], 'name'];
        yield 'unknown property' => [['name' => 'x', 'mode' => 'dark'], 'mode'];
        yield 'dark palette not an object' => [['name' => 'x', 'dark' => ['#000000']], 'dark'];
        yield 'dark palette with a bad color' => [['name' => 'x', 'dark' => ['primary' => 'black']], 'dark.primary'];
        yield 'letter spacing out of range' => [['name' => 'x', 'letterSpacing' => 1], 'letterSpacing'];
        yield 'unknown css property' => [['name' => 'x', 'css' => 'body{}'], 'css'];
        yield 'unknown color token' => [['name' => 'x', 'colors' => ['evil' => '#000000']], 'colors.evil'];
        yield 'color is not hex' => [['name' => 'x', 'colors' => ['primary' => 'red']], 'colors.primary'];
        yield 'color with css injection' => [['name' => 'x', 'colors' => ['primary' => '#000; background: url(x)']], 'colors.primary'];
        yield 'radius out of range' => [['name' => 'x', 'radius' => 5], 'radius'];
        yield 'radius not numeric' => [['name' => 'x', 'radius' => '1rem'], 'radius'];
        yield 'font size out of range' => [['name' => 'x', 'fontSize' => 40], 'fontSize'];
        yield 'font family with unsafe chars' => [['name' => 'x', 'fontFamily' => 'Inter; color: red'], 'fontFamily'];
        yield 'default not boolean' => [['name' => 'x', 'default' => 'yes'], 'default'];
        yield 'fonts not a list' => [['name' => 'x', 'fonts' => ['family' => 'A']], 'fonts'];
        yield 'too many fonts' => [['name' => 'x', 'fonts' => array_fill(0, 5, ['family' => 'A', 'src' => self::WOFF2])], 'fonts'];
        yield 'font family with a quote' => [['name' => 'x', 'fonts' => [['family' => "A'B", 'src' => self::WOFF2]]], 'fonts[0].family'];
        yield 'font source that is not a font' => [['name' => 'x', 'fonts' => [['family' => 'A', 'src' => 'data:image/png;base64,AAAA']]], 'fonts[0].src'];
        yield 'font source fetched from elsewhere' => [['name' => 'x', 'fonts' => [['family' => 'A', 'src' => 'https://evil.test/f.woff2']]], 'fonts[0].src'];
        yield 'font too large' => [['name' => 'x', 'fonts' => [['family' => 'A', 'src' => 'data:font/woff2;base64,'.str_repeat('A', 300001)]]], 'fonts[0].src'];
        yield 'unknown font weight' => [['name' => 'x', 'fonts' => [['family' => 'A', 'src' => self::WOFF2, 'weight' => '450']]], 'fonts[0].weight'];
        yield 'unknown font style' => [['name' => 'x', 'fonts' => [['family' => 'A', 'src' => self::WOFF2, 'style' => 'oblique']]], 'fonts[0].style'];
        yield 'unknown font property' => [['name' => 'x', 'fonts' => [['family' => 'A', 'src' => self::WOFF2, 'url' => 'x']]], 'fonts[0].url'];
    }
}
