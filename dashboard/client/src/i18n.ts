import {createI18N, createNS} from '@alchemy/i18n';
import * as appLangs from '../translations';
import {initReactI18next} from 'react-i18next';

const i18n = createI18N({
    initReactI18next,
    resources: createNS(appLangs),
});

export const appLocales = Object.keys(appLangs);

export default i18n;
