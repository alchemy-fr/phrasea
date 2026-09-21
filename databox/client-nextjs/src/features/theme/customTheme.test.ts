import {describe, expect, it} from 'vitest';
import {
    applyThemeVars,
    baseThemeColors,
    ClientTheme,
    compileClientThemeCss,
    normalizeClientTheme,
    resolveThemeColors,
    THEME_COLOR_TOKENS,
    THEME_CSS_VARS,
    themeMeta,
    themeToCssVars,
} from './customTheme';

const theme: ClientTheme = {
    name: 'Acme',
    default: false,
    colors: {primary: '#FF6600', border: 'not-a-color'},
    dark: {primary: '#ff8a3d'},
    radius: 1,
    fontSize: 16,
    fontFamily: 'Inter, sans-serif',
    letterSpacing: 0.01,
};

describe('custom theme', () => {
    it('completes a partial palette with the base colors of the appearance', () => {
        const light = resolveThemeColors(theme, 'light');
        expect(light.primary).toBe('#ff6600');
        expect(light.border).toBe(baseThemeColors.light.border);
        expect(Object.keys(light)).toEqual([...THEME_COLOR_TOKENS]);

        const dark = resolveThemeColors(theme, 'dark');
        expect(dark.primary).toBe('#ff8a3d');
        expect(dark.background).toBe(baseThemeColors.dark.background);
    });

    it('uses the base dark palette when there is no dark alternative', () => {
        const dark = resolveThemeColors({colors: {primary: '#ff6600'}}, 'dark');
        expect(dark).toEqual(baseThemeColors.dark);
    });

    it('turns a theme into the custom properties of <html> for an appearance', () => {
        const vars = themeToCssVars(theme, 'light');
        expect(vars['--primary']).toBe('#ff6600');
        expect(vars['--selection']).toBe(
            'color-mix(in srgb, #ff6600 12%, transparent)'
        );
        expect(vars['--radius']).toBe('1rem');
        expect(vars['--font-size']).toBe('16px');
        expect(vars['--font-sans']).toBe('Inter, sans-serif');
        expect(vars['--tracking']).toBe('0.01em');
        for (const name of Object.keys(vars)) {
            expect(THEME_CSS_VARS).toContain(name);
        }
        expect(themeToCssVars(theme, 'dark')['--primary']).toBe('#ff8a3d');
    });

    it('omits the style properties left untouched', () => {
        const vars = themeToCssVars(
            {name: 'Plain', default: false, colors: {}},
            'light'
        );
        expect(vars['--radius']).toBe('0.5rem');
        expect(vars).not.toHaveProperty('--font-size');
        expect(vars).not.toHaveProperty('--font-sans');
        expect(vars).not.toHaveProperty('--tracking');
    });

    it('applies and clears the variables on an element', () => {
        const el = document.createElement('div');
        applyThemeVars(el, themeToCssVars(theme, 'light'));
        expect(el.style.getPropertyValue('--primary')).toBe('#ff6600');
        applyThemeVars(el, {'--primary': '#000000'});
        expect(el.style.getPropertyValue('--primary')).toBe('#000000');
        expect(el.style.getPropertyValue('--font-size')).toBe('');
        applyThemeVars(el, null);
        expect(el.getAttribute('style')).toBeFalsy();
    });

    it('compiles the stylesheet like a preset: light + style, then the dark alternative', () => {
        const css = compileClientThemeCss(theme);
        expect(css).toMatch(/^\[data-theme='custom'\]\{/);
        expect(css).toContain('--primary:#ff6600;');
        expect(css).toContain('--font-sans:Inter, sans-serif;');
        expect(css).toContain('--tracking:0.01em');
        expect(css).toContain(".dark[data-theme='custom']{");
        expect(css.split(".dark[data-theme='custom']")[1]).toContain(
            '--primary:#ff8a3d'
        );
        expect(css).not.toContain('\n');
    });

    it('exposes what the menu needs', () => {
        expect(themeMeta({...theme, default: true})).toEqual({
            name: 'Acme',
            default: true,
            swatch: [baseThemeColors.light.background, '#ff6600'],
        });
    });

    describe('normalizeClientTheme', () => {
        it('accepts a valid theme and lowercases its colors', () => {
            expect(
                normalizeClientTheme({
                    name: ' Acme ',
                    default: true,
                    colors: {primary: '#FF6600', background: ''},
                    dark: {primary: '#FF8A3D'},
                    radius: 0.75,
                    fontSize: 15,
                    fontFamily: ' Inter, "Helvetica Neue", sans-serif ',
                    letterSpacing: 0.02,
                })
            ).toEqual({
                name: 'Acme',
                default: true,
                colors: {primary: '#ff6600'},
                dark: {primary: '#ff8a3d'},
                radius: 0.75,
                fontSize: 15,
                fontFamily: 'Inter, "Helvetica Neue", sans-serif',
                letterSpacing: 0.02,
            });
        });

        it('fills the defaults of a minimal theme', () => {
            expect(normalizeClientTheme({name: 'x'})).toEqual({
                name: 'x',
                default: false,
                colors: {},
            });
        });

        it.each([
            ['not an object', 'nope'],
            ['a list', []],
            ['missing name', {default: true}],
            ['blank name', {name: '  '}],
            ['name too long', {name: 'a'.repeat(51)}],
            ['the former mode property', {name: 'x', mode: 'dark'}],
            ['unknown property', {name: 'x', css: 'body{}'}],
            ['unknown color token', {name: 'x', colors: {evil: '#000000'}}],
            ['color not hex', {name: 'x', colors: {primary: 'red'}}],
            ['bad dark color', {name: 'x', dark: {primary: 'black'}}],
            ['dark not an object', {name: 'x', dark: ['#000000']}],
            [
                'css injection',
                {name: 'x', colors: {primary: '#000; background: url(x)'}},
            ],
            ['radius out of range', {name: 'x', radius: 5}],
            ['radius not a number', {name: 'x', radius: '1rem'}],
            ['font size not an integer', {name: 'x', fontSize: 14.5}],
            ['font size out of range', {name: 'x', fontSize: 40}],
            ['letter spacing out of range', {name: 'x', letterSpacing: 1}],
            [
                'unsafe font family',
                {name: 'x', fontFamily: 'Inter; color: red'},
            ],
            ['default not boolean', {name: 'x', default: 'yes'}],
        ])('rejects %s', (_label, raw) => {
            expect(normalizeClientTheme(raw)).toBeNull();
        });
    });
});
