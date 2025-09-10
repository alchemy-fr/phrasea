import type {FieldTranslations, Translations} from '@alchemy/i18n';
import {Translation} from '../types';

export function getFieldTranslationCount(
    translations: Translations | undefined,
    field: string
): number {
    if (!translations) {
        return 0;
    }

    return Object.prototype.hasOwnProperty.call(translations, field)
        ? Object.keys(translations[field]).length
        : 0;
}

export function getFieldTranslationsList(
    translations: Translations | undefined,
    field: string,
    locales?: string[],
): Translation[] {
    if (getFieldTranslationCount(translations, field) === 0) {
        return [];
    }

    const list: Translation[] = [];

    if (locales) {
        locales.forEach(locale => {
            list.push({
                locale,
                value: translations![field][locale] || '',
            });
        });
        return list;
    } else {
        Object.keys(translations![field]).forEach(locale => {
            list.push({
                locale,
                value: translations![field][locale],
            });
        });
    }

    return list;
}

export function getFieldTranslationsObject(
    translations: Translation[]
): FieldTranslations | undefined {
    const tr: FieldTranslations = {};

    translations.forEach(t => {
        tr[t.locale] = t.value;
    });

    return tr;
}
