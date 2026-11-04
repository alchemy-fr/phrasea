'use client';

import {PropsWithChildren, useEffect, useMemo} from 'react';
import {I18nextProvider} from 'react-i18next';
import {createI18n, LANG_COOKIE, LanguageCode} from './index';
import {setApiLocales} from '@/lib/api/http';
import {setPreferredLanguages} from '@/lib/utils/locale';

export function I18nProvider({
    language,
    children,
}: PropsWithChildren<{language: LanguageCode}>) {
    const i18n = useMemo(() => createI18n(language), [language]);

    useEffect(() => {
        const apply = (lng: string) => {
            document.documentElement.lang = lng;
            document.cookie = `${LANG_COOKIE}=${lng};path=/;max-age=31536000;samesite=lax`;
            setApiLocales({ui: lng});
            setPreferredLanguages([
                lng,
                ...navigator.languages.filter(l => l !== lng),
            ]);
        };
        apply(i18n.language);
        i18n.on('languageChanged', apply);

        return () => {
            i18n.off('languageChanged', apply);
        };
    }, [i18n]);

    return <I18nextProvider i18n={i18n}>{children}</I18nextProvider>;
}
