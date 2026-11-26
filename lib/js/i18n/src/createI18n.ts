import i18next, {type i18n as I18n} from 'i18next';
import {initReactI18next} from 'react-i18next';
import en from '../translations/en.json';
import fr from '../translations/fr.json';
import de from '../translations/de.json';
import es from '../translations/es.json';
import {defaultLanguage, type LanguageCode, type LanguageSet} from './index';

export type Translations = Record<string, string>;

/** The translations shared by every client (shared components, menus…) */
export const commonTranslations: Record<LanguageCode, Translations> = {
    en,
    fr,
    de,
    es,
};

/**
 * An i18next instance for a client: its own translations on top of the
 * common ones, which they override key by key. Keys are flat (`a.b.c` is one
 * key) and the default value is written next to each `t()` call.
 */
export function createI18n(
    lng: string,
    {supportedLanguages}: Pick<LanguageSet, 'supportedLanguages'>,
    translations: Partial<Record<LanguageCode, Translations>>
): I18n {
    const instance = i18next.createInstance();
    void instance.use(initReactI18next).init({
        lng,
        fallbackLng: defaultLanguage,
        supportedLngs: supportedLanguages.map(l => l.code),
        resources: Object.fromEntries(
            supportedLanguages.map(({code}) => [
                code,
                {
                    translation: {
                        ...commonTranslations[code],
                        ...translations[code],
                    },
                },
            ])
        ),
        keySeparator: false,
        nsSeparator: false,
        interpolation: {escapeValue: false},
        returnNull: false,
        initImmediate: false,
        react: {useSuspense: false},
    });

    return instance;
}
