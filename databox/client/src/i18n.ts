import {createI18N, createNS} from '@alchemy/i18n';
import * as appLangs from '../translations';

console.log('appLangs', appLangs);
const i18n = createI18N({
    resources: createNS(appLangs),
});

export default i18n;
