'use client';

import {useCallback} from 'react';
import {useTranslation} from 'react-i18next';
import type {Translations} from '@/types/api';
import {pickTranslation} from './locale';

type Translatable = {translations?: Translations | null};

/**
 * The value of a field of a publication or an asset in the language of the
 * interface (the best of its `translations`), else its default value.
 */
export function translateField(
    entity: Translatable & Record<string, unknown>,
    field: string,
    languages: readonly string[]
): string {
    const fallback = entity[field];
    const value = pickTranslation(
        entity.translations?.[field] ?? undefined,
        typeof fallback === 'string' ? fallback : '',
        languages
    );

    return value || (typeof fallback === 'string' ? fallback : '');
}

export function useTranslateField() {
    const {i18n} = useTranslation();
    const language = i18n.language;

    return useCallback(
        <T extends Translatable>(entity: T, field: keyof T & string): string =>
            translateField(
                entity as Translatable & Record<string, unknown>,
                field,
                [
                    language,
                    ...(typeof navigator !== 'undefined'
                        ? navigator.languages.filter(l => l !== language)
                        : []),
                ]
            ),
        [language]
    );
}
