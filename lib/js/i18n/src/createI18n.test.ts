import {describe, expect, it} from 'vitest';
import {defineLanguages} from './index';
import {commonTranslations, createI18n} from './createI18n';

describe('defineLanguages', () => {
    const languages = defineLanguages(['en', 'fr'], 'app_lang');

    it('keeps the languages of the client only', () => {
        expect(languages.supportedLanguages.map(l => l.code)).toEqual([
            'en',
            'fr',
        ]);
        expect(languages.isSupportedLanguage('fr')).toBe(true);
        expect(languages.isSupportedLanguage('de')).toBe(false);
        expect(languages.isSupportedLanguage(undefined)).toBe(false);
    });

    it('falls back to the default language', () => {
        expect(languages.resolveLanguage('fr')).toBe('fr');
        expect(languages.resolveLanguage('de')).toBe('en');
        expect(languages.resolveLanguage(undefined)).toBe('en');
    });
});

describe('createI18n', () => {
    const languages = defineLanguages(['en', 'fr', 'de', 'es'], 'app_lang');

    it('merges the translations of the client over the common ones', () => {
        const i18n = createI18n('fr', languages, {
            fr: {'app.title': 'Mon appli', 'user.login': 'Entrer'},
        });

        expect(i18n.t('app.title')).toBe('Mon appli');
        expect(i18n.t('user.login')).toBe('Entrer');
        expect(i18n.t('user.logout')).toBe(
            commonTranslations.fr['user.logout']
        );
    });

    it('falls back to English for a key missing in a language', () => {
        const i18n = createI18n('de', languages, {en: {'app.title': 'App'}});

        expect(i18n.t('app.title')).toBe('App');
    });

    it('translates every common key in every language', () => {
        const keys = Object.keys(commonTranslations.en).sort();
        for (const lng of ['fr', 'de', 'es'] as const) {
            expect(Object.keys(commonTranslations[lng]).sort()).toEqual(keys);
        }
    });
});
