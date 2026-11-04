'use client';

import {
    PropsWithChildren,
    ReactElement,
    ReactNode,
    useEffect,
    useMemo,
} from 'react';
import type {i18n as I18n} from 'i18next';
import {I18nextProvider as BaseI18nextProvider} from 'react-i18next';
import type {LanguageCode} from './index';

/**
 * react-i18next declares no peer dependency on the React types: it gets the
 * ones hoisted in the workspace, which may be the React 18 types of the
 * other clients, and its `children` then refuse React 19 nodes.
 */
const I18nextProvider = BaseI18nextProvider as unknown as (props: {
    i18n: I18n;
    children?: ReactNode;
}) => ReactElement;

/**
 * Provides the i18next instance of the page and follows the language: the
 * `lang` of <html>, the cookie read by the root layout on the next request,
 * and `onLanguageChange` for what the client derives from it (the locale of
 * its API calls…).
 */
export function I18nProvider({
    language,
    cookie,
    createI18n,
    onLanguageChange,
    children,
}: PropsWithChildren<{
    language: LanguageCode;
    cookie: string;
    createI18n: (lng: string) => I18n;
    onLanguageChange?: (lng: string) => void;
}>) {
    const i18n = useMemo(() => createI18n(language), [createI18n, language]);

    useEffect(() => {
        const apply = (lng: string) => {
            document.documentElement.lang = lng;
            document.cookie = `${cookie}=${lng};path=/;max-age=31536000;samesite=lax`;
            onLanguageChange?.(lng);
        };
        apply(i18n.language);
        i18n.on('languageChanged', apply);

        return () => {
            i18n.off('languageChanged', apply);
        };
    }, [i18n, cookie, onLanguageChange]);

    return <I18nextProvider i18n={i18n}>{children}</I18nextProvider>;
}
