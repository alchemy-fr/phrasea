import {defineLanguages} from '@alchemy/next-i18n';

export type {LanguageCode} from '@alchemy/next-i18n';

export const languages = defineLanguages(['en', 'fr', 'de'], 'expose_lang');
