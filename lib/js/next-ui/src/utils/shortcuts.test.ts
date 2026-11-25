import {afterEach, describe, expect, it, vi} from 'vitest';
import {formatModifier, formatShortcut, isMacPlatform} from './shortcuts';

describe('formatShortcut', () => {
    it('uses the macOS symbols on Mac', () => {
        expect(formatShortcut('u', true)).toBe('⌘U');
        expect(formatShortcut('A', true, {shift: true})).toBe('⇧⌘A');
    });

    it('uses Ctrl elsewhere', () => {
        expect(formatShortcut('u', false)).toBe('Ctrl+U');
        expect(formatShortcut('A', false, {shift: true})).toBe('Ctrl+Shift+A');
    });
});

describe('formatModifier', () => {
    it('formats the modifier alone', () => {
        expect(formatModifier(true)).toBe('⌘');
        expect(formatModifier(true, {shift: true})).toBe('⇧⌘');
        expect(formatModifier(false)).toBe('Ctrl');
        expect(formatModifier(false, {shift: true})).toBe('Ctrl+Shift');
    });

    it('names Shift in the language of the user, except on macOS', () => {
        expect(formatModifier(false, {shift: true, shiftLabel: 'Maj'})).toBe(
            'Ctrl+Maj'
        );
        expect(
            formatShortcut('a', false, {shift: true, shiftLabel: 'Maj'})
        ).toBe('Ctrl+Maj+A');
        expect(formatModifier(true, {shift: true, shiftLabel: 'Maj'})).toBe(
            '⇧⌘'
        );
    });
});

describe('isMacPlatform', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        delete (navigator as {userAgentData?: unknown}).userAgentData;
    });

    it.each([
        ['MacIntel', true],
        ['iPad', true],
        ['iPhone', true],
        ['Win32', false],
        ['Linux x86_64', false],
    ])('reads navigator.platform "%s"', (platform, expected) => {
        vi.spyOn(navigator, 'platform', 'get').mockReturnValue(platform);

        expect(isMacPlatform()).toBe(expected);
    });

    it.each([
        ['macOS', true],
        ['Windows', false],
        ['Linux', false],
    ])(
        'prefers navigator.userAgentData.platform "%s"',
        (platform, expected) => {
            vi.spyOn(navigator, 'platform', 'get').mockReturnValue(
                expected ? 'Win32' : 'MacIntel'
            );
            Object.defineProperty(navigator, 'userAgentData', {
                value: {platform},
                configurable: true,
            });

            expect(isMacPlatform()).toBe(expected);
        }
    );
});
