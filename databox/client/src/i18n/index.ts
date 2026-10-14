import i18next, {type i18n as I18n} from 'i18next';
import {initReactI18next} from 'react-i18next';
import en from './locales/en.json';
import fr from './locales/fr.json';
import de from './locales/de.json';
import es from './locales/es.json';
import {defaultLanguage, supportedLanguages} from './config';

export * from './config';

export function createI18n(lng: string): I18n {
    const instance = i18next.createInstance();
    void instance.use(initReactI18next).init({
        lng,
        fallbackLng: defaultLanguage,
        supportedLngs: supportedLanguages.map(l => l.code),
        resources: {
            en: {translation: en},
            fr: {translation: fr},
            de: {translation: de},
            es: {translation: es},
        },
        keySeparator: false,
        nsSeparator: false,
        interpolation: {escapeValue: false},
        returnNull: false,
        initImmediate: false,
        react: {useSuspense: false},
    });

    return instance;
}
