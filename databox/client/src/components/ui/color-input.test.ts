import {describe, expect, it} from 'vitest';
import {hexToHsv, hsvToHex, isValidHex} from './color-input';

describe('color conversions', () => {
    it.each([
        '#000000',
        '#ffffff',
        '#ff0000',
        '#00ff00',
        '#0000ff',
        '#3b82f6',
        '#6b7280',
        '#d946ef',
    ])('round-trips %s', hex => {
        expect(hsvToHex(hexToHsv(hex))).toBe(hex);
    });

    it('computes the hue', () => {
        expect(hexToHsv('#00ff00')).toEqual({h: 120, s: 1, v: 1});
        expect(hexToHsv('#808080').s).toBe(0);
    });

    it('validates hex codes', () => {
        expect(isValidHex('#A1b2C3')).toBe(true);
        expect(isValidHex('#abc')).toBe(false);
        expect(isValidHex('')).toBe(false);
        expect(isValidHex(undefined)).toBe(false);
    });
});
