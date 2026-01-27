import {createI18N, createNS} from '@alchemy/i18n';
import * as appLangs from '../translations';
import {getBestFieldTranslatedValue} from '@alchemy/i18n/src/Locale/localeHelper';
import type {Translations} from '@alchemy/i18n';
import {initReactI18next} from 'react-i18next';

const i18n = createI18N({
    initReactI18next,
    resources: createNS(appLangs),
});

export const appLocales = Object.keys(appLangs);

export default i18n;

export function getTranslatedTitle({
    title,
    translations,
}: {
    title: string;
    translations?: Translations;
}): string {
    return getBestFieldTranslatedValue(translations, 'title', title);
}

export function getTranslatedDescription({
    description,
    translations,
}: {
    description?: string | undefined;
    translations?: Translations;
}): string | undefined {
    return getBestFieldTranslatedValue(
        translations,
        'description',
        description
    );
}
