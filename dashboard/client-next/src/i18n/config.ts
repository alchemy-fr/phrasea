import {defineLanguages} from '@alchemy/next-i18n';

export type {LanguageCode} from '@alchemy/next-i18n';

export const languages = defineLanguages(
    ['en', 'fr', 'de', 'es'],
    'dashboard_lang'
);
