<?php

declare(strict_types=1);

namespace App\Tests\Unit\Util;

use App\Util\NaturalSortKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Same vectors as databox/client/src/lib/utils/naturalSortKey.test.ts.
 */
class NaturalSortKeyTest extends TestCase
{
    #[DataProvider('getKeys')]
    public function testFromString(string $expected, ?string $input): void
    {
        $this->assertSame($expected, NaturalSortKey::fromString($input));
    }

    public static function getKeys(): array
    {
        return [
            'null' => ['', null],
            'empty' => ['', ''],
            'case' => ['hello world', 'Hello WORLD'],
            'accents' => ['e n a', 'É ñ Å'],
            'dotted capital I' => ['i', 'İ'],
            'greek' => ["\u{3BF}\u{3B4}\u{3BF}\u{3C2}", "\u{39F}\u{394}\u{39F}\u{3A3}"],
            'spaces' => ['a b c d', "  a\tb\u{A0}c\u{3000}\n d  "],
            'leading zeros' => ['017', '007'],
            'zero' => ['010', '0'],
            'zeros' => ['010', '000'],
            'digit runs' => ['a011b0222', 'a1b22'],
            'number' => ['lot 0210', 'Lot 10'],
        ];
    }

    public function testTruncation(): void
    {
        $this->assertSame(str_repeat('e', NaturalSortKey::MAX_LENGTH), NaturalSortKey::fromString(str_repeat('é', 1200)));
        $this->assertSame(str_repeat('a', NaturalSortKey::MAX_LENGTH), NaturalSortKey::fromString(str_repeat('A', 1200)));
    }

    public function testLongDigitRunsKeepTheirOrder(): void
    {
        $this->assertSame(-1, NaturalSortKey::compare(
            NaturalSortKey::fromString(str_repeat('9', 20)),
            NaturalSortKey::fromString('1'.str_repeat('0', 20)),
        ));
    }

    public function testCodePointOrder(): void
    {
        // U+FF01 is before U+1F600 by code point, after it by UTF-16 code unit
        $this->assertSame(-1, NaturalSortKey::compare("\u{FF01}", "\u{1F600}"));
        $this->assertSame(1, NaturalSortKey::compare("\u{1F600}", "\u{FF01}"));
        $this->assertSame(0, NaturalSortKey::compare('a', 'a'));
        $this->assertSame(-1, NaturalSortKey::compare('a', 'ab'));
    }

    public function testNaturalOrder(): void
    {
        $names = ['Lot 10', 'lot 2', 'Lot 1', 'Été 2024', 'ete 2023', 'Zèbre', 'apple', 'Äpfel', 'B', '  Beta  ', '_misc', '10 ans', '9 vies'];

        usort($names, static fn (string $a, string $b): int => NaturalSortKey::compare(NaturalSortKey::fromString($a), NaturalSortKey::fromString($b)));

        $this->assertSame(['9 vies', '10 ans', '_misc', 'Äpfel', 'apple', 'B', '  Beta  ', 'ete 2023', 'Été 2024', 'Lot 1', 'lot 2', 'Lot 10', 'Zèbre'], $names);
    }
}
