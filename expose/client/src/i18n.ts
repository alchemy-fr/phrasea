import {createI18N, createNS} from '@alchemy/i18n';
import * as appLangs from '../translations';
import {getBestTranslatedValue} from '@alchemy/i18n/src/Locale/localeHelper';
import type {Translations} from '@alchemy/i18n';

const i18n = createI18N({
    resources: createNS(appLangs),
});

export default i18n;

export function getTranslatedTitle({
    title,
    translations,
}: {
    title: string;
    translations?: Translations;
}): string {
    return getBestTranslatedValue(translations, 'title', title);
}

export function getTranslatedDescription({
    description,
    translations,
}: {
    description?: string | undefined;
    translations?: Translations;
}): string | undefined {
    return getBestTranslatedValue(translations, 'description', description);
}
