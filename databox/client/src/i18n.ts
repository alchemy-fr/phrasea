import i18n, {ResourceLanguage} from 'i18next';
import {initReactI18next} from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import {defaultLocale, locales} from "./lib/locales";
import * as appLangs from './locales/app';

const resources: { [language: string]: ResourceLanguage; } = {};

function addNS(ns: string, r: {[language: string]: ResourceLanguage}): void {
    locales.forEach(l => {
        if (!resources[l]) {
            resources[l] = {[ns]: r[l]};
        } else {
            resources[l][ns] = r[l];
        }
    });
}
addNS('app', appLangs);

i18n
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
        defaultNS: 'app',
        supportedLngs: locales,
        fallbackLng: defaultLocale,
        interpolation: {
            escapeValue: false, // not needed for react as it escapes by default
        },
        resources,
    });

function normalizeHTMLLocale(l: string): string {
    return l.replace(/_/g, '-');
}

function setHtmlLangAttr(l: string): void {
    document.documentElement.setAttribute('lang', normalizeHTMLLocale(l));
}

function languageChanged(lng: string): void {
    setHtmlLangAttr(lng);
}

languageChanged(i18n.language);
i18n.on('languageChanged', languageChanged);

export default i18n;
