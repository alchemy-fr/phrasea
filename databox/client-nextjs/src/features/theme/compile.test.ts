import {describe, expect, it} from 'vitest';
import {compileStackTheme} from './compile';

const theme = {
    name: 'Acme',
    default: true,
    colors: {primary: '#ff6600'},
    dark: {primary: '#ff8a3d'},
    radius: 1,
};

describe('compileStackTheme', () => {
    it('compiles the theme stored as a JSON string by the configurator', () => {
        const compiled = compileStackTheme({
            logo: {src: 'x'},
            databox: {theme: JSON.stringify(theme)},
        });
        expect(compiled?.meta).toEqual({
            name: 'Acme',
            default: true,
            swatch: ['#f9fafb', '#ff6600'],
        });
        expect(compiled?.css).toContain("[data-theme='custom']{");
        expect(compiled?.css).toContain('--primary:#ff6600;');
        expect(compiled?.css).toContain('--radius:1rem');
        expect(compiled?.css).toContain(".dark[data-theme='custom']{");
        expect(compiled?.css).toContain('--primary:#ff8a3d');
    });

    it('accepts an already decoded theme', () => {
        expect(compileStackTheme({databox: {theme}})?.meta.name).toBe('Acme');
    });

    it('ignores a missing, malformed or invalid theme', () => {
        expect(compileStackTheme({})).toBeNull();
        expect(compileStackTheme(null)).toBeNull();
        expect(compileStackTheme({databox: {}})).toBeNull();
        expect(compileStackTheme({databox: {theme: '{oops'}})).toBeNull();
        expect(
            compileStackTheme({
                databox: {theme: {...theme, colors: {primary: 'url(x)'}}},
            })
        ).toBeNull();
    });
});
