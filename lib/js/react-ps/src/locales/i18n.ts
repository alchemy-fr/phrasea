import i18n, {ResourceLanguage} from 'i18next';
import {initReactI18next} from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import * as loginLangs from './domains/login';
import {defaultLocale, locales} from "./locales";

const resources: { [language: string]: ResourceLanguage; } = {};

function addNS(ns: string, r: { [language: string]: ResourceLanguage }): void {
    locales.forEach((l: string) => {
        if (!resources[l]) {
            resources[l] = {[ns]: r[l]};
        } else {
            resources[l][ns] = r[l];
        }
    });
}

addNS('login', loginLangs);

i18n
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
        defaultNS: 'login',
        supportedLngs: locales,
        fallbackLng: defaultLocale,
        interpolation: {
            escapeValue: false, // not needed for react as it escapes by default
        },
        resources,
    });

export default i18n;
