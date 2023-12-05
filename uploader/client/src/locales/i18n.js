import i18next from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import {initReactI18next} from 'react-i18next';
import * as languages from './';
const i18n = i18next.createInstance();

const ns = 'translation';

const resources = {};
Object.keys(languages).forEach(l => {
    resources[l] = {
        [ns]: languages[l],
    };
});

i18n.use(LanguageDetector)
    .use(initReactI18next)
    .init({
        debug: false,
        resources,
        fallbackLng: 'en',
        ns: [ns],
        defaultNs: ns,
        detection: {
            order: ['querystring', 'navigator'],
            lookupQuerystring: 'lng',
        },
        react: {
            bindI18n: 'languageChanged',
            bindI18nStore: '',
            transEmptyNodeValue: '',
            transSupportBasicHtmlNodes: true,
            transKeepBasicHtmlNodesFor: ['br', 'strong', 'i'],
            useSuspense: false,
        },
    });

export default i18n;
