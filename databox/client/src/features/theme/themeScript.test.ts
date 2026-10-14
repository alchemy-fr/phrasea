import {describe, expect, it} from 'vitest';
import {
    buildPrepaintScript,
    prepaintOptionsFor,
    prepaintTheme,
} from './themeScript';

function fakeWindow() {
    const store = new Map<string, string>();
    const html = document.createElement('html');

    return {
        html,
        store,
        win: {
            document: {documentElement: html},
            localStorage: {getItem: (k: string) => store.get(k) ?? null},
        } as unknown as Window,
    };
}

const presets = ['ocean', 'mono'];
const noCustom = prepaintOptionsFor(undefined, presets);
const withCustom = prepaintOptionsFor(
    {name: 'Acme', default: true, swatch: ['#000000', '#ffffff']},
    presets
);

describe('pre-paint theme script', () => {
    it('sets data-theme from the stored choice', () => {
        const f = fakeWindow();
        f.store.set('dbx.theme', 'ocean');
        prepaintTheme(f.win, noCustom);
        expect(f.html.getAttribute('data-theme')).toBe('ocean');
    });

    it('leaves the base palette when nothing is stored or the theme is unknown', () => {
        const f = fakeWindow();
        prepaintTheme(f.win, noCustom);
        expect(f.html.hasAttribute('data-theme')).toBe(false);

        const stale = fakeWindow();
        stale.store.set('dbx.theme', 'midnight');
        prepaintTheme(stale.win, noCustom);
        expect(stale.html.hasAttribute('data-theme')).toBe(false);

        const custom = fakeWindow();
        custom.store.set('dbx.theme', 'custom');
        prepaintTheme(custom.win, noCustom);
        expect(custom.html.hasAttribute('data-theme')).toBe(false);
    });

    it('applies the organisation theme by default when the user never chose', () => {
        expect(withCustom.defaultTheme).toBe('custom');
        expect(noCustom.defaultTheme).toBe('default');
        const f = fakeWindow();
        prepaintTheme(f.win, withCustom);
        expect(f.html.getAttribute('data-theme')).toBe('custom');

        const chose = fakeWindow();
        chose.store.set('dbx.theme', 'mono');
        prepaintTheme(chose.win, withCustom);
        expect(chose.html.getAttribute('data-theme')).toBe('mono');
    });

    it('survives a missing storage', () => {
        const f = fakeWindow();
        expect(() =>
            prepaintTheme(
                {document: {documentElement: f.html}} as unknown as Window,
                noCustom
            )
        ).not.toThrow();
    });

    it('serializes to a self-contained script', () => {
        const script = buildPrepaintScript(withCustom);
        expect(script).toContain('"knownThemes":["ocean","mono","custom"]');
        expect(script).not.toMatch(/\bimport\b|require\(/);
        const f = fakeWindow();
        f.store.set('dbx.theme', 'ocean');
        new Function('window', script)(f.win);
        expect(f.html.getAttribute('data-theme')).toBe('ocean');
    });
});
