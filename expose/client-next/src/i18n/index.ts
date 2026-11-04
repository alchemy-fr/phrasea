import type {i18n as I18n} from 'i18next';
import {createI18n as createClientI18n} from '@alchemy/next-i18n/createI18n';
import en from './locales/en.json';
import fr from './locales/fr.json';
import de from './locales/de.json';
import {languages} from './config';

export * from './config';

export function createI18n(lng: string): I18n {
    return createClientI18n(lng, languages, {en, fr, de});
}
