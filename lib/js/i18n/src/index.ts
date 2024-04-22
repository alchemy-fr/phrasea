import i18n, {ResourceLanguage} from 'i18next';
import {I18nextProviderProps, initReactI18next} from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

type Resources = {[language: string]: ResourceLanguage};

type Options = {
    resources: Resources,
    defaultNS?: I18nextProviderProps['defaultNS'];
    locales?: string[];
    defaultLocale?: string;
    onLanguageChanged?: (lng: string | undefined) => void;
}

const defaultLocales = ['en', 'fr', 'de', 'es', 'zh'];
const rootDefaultLocale = 'en';
const rootDefaultNs = 'app';

export function createI18N({
    resources,
    onLanguageChanged,
    defaultNS = rootDefaultNs,
    locales = defaultLocales,
    defaultLocale = rootDefaultLocale,
}: Options) {
    i18n.use(LanguageDetector)
        .use(initReactI18next)
        .init({
            defaultNS,
            supportedLngs: locales,
            fallbackLng: defaultLocale,
            interpolation: {
                escapeValue: false, // not needed for react as it escapes by default
            },
            resources,
        });


    languageChanged(i18n.language);
    i18n.on('languageChanged', (lng: string | undefined): void => {
        languageChanged(lng);
        onLanguageChanged && onLanguageChanged(lng);
    });

    return i18n;
}

export function normalizeHTMLLocale(l: string): string {
    return l.replace(/_/g, '-');
}

export function setHtmlLangAttr(lng: string | undefined): void {
    if (lng) {
        document.documentElement.setAttribute('lang', normalizeHTMLLocale(lng));
    }
}

export function languageChanged(lng: string | undefined): void {
    setHtmlLangAttr(lng);
}

export function appendNS(resources: Resources, ns: string, r: {[language: string]: ResourceLanguage}, locales = defaultLocales): void {
    locales.forEach(l => {
        if (!resources[l]) {
            resources[l] = {[ns]: r[l]};
        } else {
            resources[l][ns] = r[l];
        }
    });
}

export function createNS(r: {[language: string]: ResourceLanguage}, ns: string = rootDefaultNs, locales = defaultLocales): Resources {
    const resources: Resources = {};
    locales.forEach(l => {
        if (!resources[l]) {
            resources[l] = {[ns]: r[l]};
        } else {
            resources[l][ns] = r[l];
        }
    });

    return resources;
}
