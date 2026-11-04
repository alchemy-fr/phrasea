'use client';

import type {PropsWithChildren} from 'react';
import {I18nProvider as BaseI18nProvider} from '@alchemy/next-i18n/I18nProvider';
import {setApiLocales} from '@/lib/api/http';
import {setPreferredLanguages} from '@/lib/utils/locale';
import {createI18n, languages, type LanguageCode} from './index';

/** The locale of the API calls and of the formatting follow the language */
function applyLanguage(lng: string): void {
    setApiLocales({ui: lng});
    setPreferredLanguages([lng, ...navigator.languages.filter(l => l !== lng)]);
}

export function I18nProvider({
    language,
    children,
}: PropsWithChildren<{language: LanguageCode}>) {
    return (
        <BaseI18nProvider
            language={language}
            cookie={languages.cookie}
            createI18n={createI18n}
            onLanguageChange={applyLanguage}
        >
            {children}
        </BaseI18nProvider>
    );
}
