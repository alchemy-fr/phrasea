import {createI18N, createNS} from '@alchemy/i18n';
import {setCurrentLocale} from '@alchemy/i18n/src/Locale/localeHelper.ts';
import * as appLangs from '../translations';
import {initReactI18next} from 'react-i18next';
import {apiClient} from './init.ts';
import moment from 'moment';
// CJS locale paths: the ESM builds (moment/dist/locale/*) cannot be
// resolved by Node when moment is externalized during SSR
import 'moment/locale/zh-cn';
import 'moment/locale/de';
import 'moment/locale/es';
import 'moment/locale/it';
import 'moment/locale/fr';
import 'moment/locale/pt';

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
