import {describe, expect, it} from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import {
    CUSTOM_THEME_ID,
    DEFAULT_THEME_ID,
    isKnownThemeId,
    themePresets,
} from './presets';
import {HEX_COLOR_RE} from './customTheme';
import {themeFonts} from './fonts';

describe('theme presets', () => {
    it('offers ten distinct presets with a valid swatch', () => {
        expect(themePresets).toHaveLength(10);
        expect(new Set(themePresets.map(p => p.id)).size).toBe(10);
        for (const preset of themePresets) {
            expect(preset.swatch[0]).toMatch(HEX_COLOR_RE);
            expect(preset.swatch[1]).toMatch(HEX_COLOR_RE);
            expect([DEFAULT_THEME_ID, CUSTOM_THEME_ID]).not.toContain(
                preset.id
            );
        }
    });

    it('has a light palette, a dark alternative and a style in globals.css for every preset', () => {
        const css = fs.readFileSync(
            path.resolve(__dirname, '../../app/globals.css'),
            'utf8'
        );
        for (const preset of themePresets) {
            const start = css.indexOf(`[data-theme='${preset.id}'] {`);
            expect(start).toBeGreaterThan(-1);
            const block = css.slice(start, css.indexOf('}', start));
            expect(block).toContain('--radius:');
            expect(block).toContain('--font-sans:');
            expect(block).toContain('--font-size:');
            expect(css).toContain(`.dark[data-theme='${preset.id}'] {`);
        }
        // Presets differ by more than their colors: each one is set in a
        // Google font of its own, served through app/fonts.ts
        const fonts = themePresets.map(p => {
            const start = css.indexOf(`[data-theme='${p.id}'] {`);

            return css
                .slice(start, css.indexOf('}', start))
                .match(/--font-sans:\s*var\((--font-[a-z-]+)\)/)?.[1];
        });
        expect(fonts.filter(Boolean)).toHaveLength(themePresets.length);
        expect(new Set(fonts).size).toBe(themePresets.length);
        for (const cssVar of fonts) {
            expect(themeFonts.map(f => f.cssVar)).toContain(cssVar);
        }
    });

    it('knows which theme ids are valid', () => {
        expect(isKnownThemeId('default', false)).toBe(true);
        expect(isKnownThemeId('ocean', false)).toBe(true);
        expect(isKnownThemeId('custom', false)).toBe(false);
        expect(isKnownThemeId('custom', true)).toBe(true);
        expect(isKnownThemeId('midnight', true)).toBe(false);
    });
});
