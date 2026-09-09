'use client';

import {PropsWithChildren, useEffect} from 'react';
import {useTheme} from 'next-themes';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@/lib/auth/AuthProvider';
import {usePreferencesStore} from './store';
import {FullPageLoader} from '@/components/ui/loader';
import {setApiLocales} from '@/lib/api/http';

/**
 * Loads server-side user preferences once the session is known, then applies
 * the persisted theme / data locale before rendering the app.
 */
export function UserPreferencesGate({children}: PropsWithChildren) {
    const {status} = useAuth();
    const {loaded, load, preferences} = usePreferencesStore();
    const {setTheme} = useTheme();
    const {t} = useTranslation();

    useEffect(() => {
        if (status !== 'loading') {
            void load(status === 'authenticated');
        }
    }, [status, load]);

    useEffect(() => {
        if (preferences.theme) {
            setTheme(preferences.theme);
        }
    }, [preferences.theme, setTheme]);

    useEffect(() => {
        setApiLocales({data: preferences.dataLocale});
    }, [preferences.dataLocale]);

    if (!loaded) {
        return (
            <FullPageLoader
                label={t('preferences.loading', 'Loading user preferences…')}
            />
        );
    }

    return <>{children}</>;
}
