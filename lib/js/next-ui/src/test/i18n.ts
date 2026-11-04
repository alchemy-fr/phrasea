import type {i18n as I18n} from 'i18next';
import {defineLanguages} from '@alchemy/next-i18n';
import {createI18n as createClientI18n} from '@alchemy/next-i18n/createI18n';

const languages = defineLanguages(['en', 'fr', 'de', 'es'], 'lang');

/** An i18next instance with the common translations only */
export function createI18n(lng: string): I18n {
    return createClientI18n(lng, languages, {});
}
