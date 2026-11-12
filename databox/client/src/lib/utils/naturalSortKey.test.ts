import {describe, expect, it} from 'vitest';
import {compareCodePoints, naturalSortKey} from './naturalSortKey';

// Same vectors as databox/api/tests/unit/Util/NaturalSortKeyTest.php
describe('naturalSortKey', () => {
    it.each([
        ['', null],
        ['', undefined],
        ['', ''],
        ['hello world', 'Hello WORLD'],
        ['e n a', 'É ñ Å'],
        ['i', 'İ'],
        ['οδος', 'ΟΔΟΣ'],
        ['a b c d', '  a\tb c　\n d  '],
        ['017', '007'],
        ['010', '0'],
        ['010', '000'],
        ['a011b0222', 'a1b22'],
        ['lot 0210', 'Lot 10'],
    ])('gives %j for %j', (expected, input) => {
        expect(naturalSortKey(input)).toBe(expected);
    });

    it('truncates to 1000 code points', () => {
        expect(naturalSortKey('é'.repeat(1200))).toBe('e'.repeat(1000));
        expect(naturalSortKey('😀'.repeat(1200))).toBe('😀'.repeat(1000));
    });

    it('keeps the order of long digit runs', () => {
        expect(
            compareCodePoints(
                naturalSortKey('9'.repeat(20)),
                naturalSortKey('1' + '0'.repeat(20))
            )
        ).toBe(-1);
    });

    it('sorts in natural order', () => {
        const names = [
            'Lot 10',
            'lot 2',
            'Lot 1',
            'Été 2024',
            'ete 2023',
            'Zèbre',
            'apple',
            'Äpfel',
            'B',
            '  Beta  ',
            '_misc',
            '10 ans',
            '9 vies',
        ];

        names.sort((a, b) =>
            compareCodePoints(naturalSortKey(a), naturalSortKey(b))
        );

        expect(names).toEqual([
            '9 vies',
            '10 ans',
            '_misc',
            'Äpfel',
            'apple',
            'B',
            '  Beta  ',
            'ete 2023',
            'Été 2024',
            'Lot 1',
            'lot 2',
            'Lot 10',
            'Zèbre',
        ]);
    });
});

describe('compareCodePoints', () => {
    it('compares by code point', () => {
        // U+FF01 is before U+1F600 by code point, after it by UTF-16 code unit
        expect(compareCodePoints('！', '😀')).toBe(-1);
        expect(compareCodePoints('😀', '！')).toBe(1);
        expect(compareCodePoints('😀', '😁')).toBe(-1);
        expect(compareCodePoints('a', 'a')).toBe(0);
        expect(compareCodePoints('a', 'ab')).toBe(-1);
        expect(compareCodePoints('b', 'ab')).toBe(1);
    });
});
