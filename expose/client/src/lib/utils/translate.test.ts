import {describe, expect, it} from 'vitest';
import {translateField} from './translate';

describe('translateField', () => {
    const publication = {
        title: 'Default',
        translations: {title: {fr: 'Défaut', de_DE: 'Standard'}},
    };

    it('picks the translation of the language', () => {
        expect(translateField(publication, 'title', ['fr'])).toBe('Défaut');
        expect(translateField(publication, 'title', ['de'])).toBe('Standard');
        expect(translateField(publication, 'title', ['fr-CA'])).toBe('Défaut');
    });

    it('falls back to the default value', () => {
        expect(translateField(publication, 'title', ['es'])).toBe('Default');
        expect(translateField({title: 'X'}, 'title', ['fr'])).toBe('X');
        expect(translateField({title: null}, 'title', ['fr'])).toBe('');
    });
});
