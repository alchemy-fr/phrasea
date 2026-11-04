'use client';

import type {PropsWithChildren} from 'react';
import {I18nProvider as BaseI18nProvider} from '@alchemy/next-i18n/I18nProvider';
import {createI18n, languages, type LanguageCode} from './index';

export function I18nProvider({
    language,
    children,
}: PropsWithChildren<{language: LanguageCode}>) {
    return (
        <BaseI18nProvider
            language={language}
            cookie={languages.cookie}
            createI18n={createI18n}
        >
            {children}
        </BaseI18nProvider>
    );
}
