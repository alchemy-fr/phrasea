import {createI18N, createNS} from '@alchemy/i18n';
import {setCurrentLocale} from '@alchemy/i18n/src/Locale/localeHelper.ts';
import * as appLangs from '../translations';
import {initReactI18next} from 'react-i18next';
import moment from 'moment/moment';
import apiClient from './api/api-client.ts';

const i18n = createI18N({
    initReactI18next,
    resources: createNS(appLangs),
    onLanguageChanged: lng => {
        if (lng) {
            onUpdateLocale(i18n.language);
        }
    },
});

if (i18n.language) {
    onUpdateLocale(i18n.language);
}

function onUpdateLocale(locale: string) {
    moment().locale(locale);
    apiClient.setApiLocale(locale);
    setCurrentLocale(locale);
}

export default i18n;
