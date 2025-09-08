import {createI18N, createNS} from '@alchemy/i18n';
import {setCurrentLocale} from '@alchemy/i18n/src/Locale/localeHelper.ts';
import * as appLangs from '../translations';
import {initReactI18next} from 'react-i18next';
import apiClient from './api/api-client.ts';
import moment from 'moment';
import 'moment/dist/locale/zh-cn.js';
import 'moment/dist/locale/de.js';
import 'moment/dist/locale/es.js';
import 'moment/dist/locale/it.js';
import 'moment/dist/locale/fr.js';
import 'moment/dist/locale/pt.js';

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
    const momentLocales: Record<string, string> = {
        zh: 'zh-cn',
    };

    moment.locale(momentLocales[locale] ?? locale);
    apiClient.setApiLocale(locale);
    setCurrentLocale(locale);
}

export default i18n;
