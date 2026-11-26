import {defineLanguages} from '@alchemy/i18n';

export type {LanguageCode} from '@alchemy/i18n';

export const languages = defineLanguages(['en', 'fr', 'de', 'es'], 'dbx_lang');
