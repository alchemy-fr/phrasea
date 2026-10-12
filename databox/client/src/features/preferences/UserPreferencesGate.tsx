'use client';

import {PropsWithChildren, useEffect, useRef, useState} from 'react';
import {useTheme} from 'next-themes';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@/lib/auth/AuthProvider';
import {usePreferencesStore} from './store';
import {FullPageLoader} from '@/components/ui/loader';
import {setApiLocales} from '@/lib/api/http';
import {useThemeStore} from '@/features/theme/themeStore';
import {useProfileStore} from '@/features/profiles/profileStore';

/**
 * Loads server-side user preferences once the session is known, then applies
 * the persisted theme / data locale before rendering the app, and loads the
 * display profile they select (the attribute lists, grid cards and default
 * search filters follow it) before the first search.
 */
export function UserPreferencesGate({children}: PropsWithChildren) {
    const {status} = useAuth();
    const {loaded, load, preferences, authenticated} = usePreferencesStore();
    const {setTheme} = useTheme();
    const {t} = useTranslation();

    useEffect(() => {
        if (status !== 'loading') {
            void load(status === 'authenticated');
        }
    }, [status, load]);

    // The profile store reads the selected profile from the preferences
    const loadProfiles = useProfileStore(s => s.load);
    // Only the first load holds the app back: a later one must not unmount it
    const [profilesReady, setProfilesReady] = useState(false);
    useEffect(() => {
        if (loaded && authenticated) {
            // A failure leaves the app without profile, not stuck loading
            loadProfiles()
                .catch(() => undefined)
                .finally(() => setProfilesReady(true));
        }
    }, [loaded, authenticated, loadProfiles]);

    // Each persisted value is applied once: next-themes's setTheme changes
    // identity with the current theme, re-running these effects would revert
    // a change made on purpose without the preference (e.g. a preview).
    const appliedTheme = useRef<string>(undefined);
    useEffect(() => {
        if (preferences.theme && preferences.theme !== appliedTheme.current) {
            appliedTheme.current = preferences.theme;
            setTheme(preferences.theme);
        }
    }, [preferences.theme, setTheme]);

    const setPalette = useThemeStore(s => s.setTheme);
    const appliedPalette = useRef<string>(undefined);
    useEffect(() => {
        if (
            preferences.palette &&
            preferences.palette !== appliedPalette.current
        ) {
            appliedPalette.current = preferences.palette;
            setPalette(preferences.palette, {persist: false});
        }
    }, [preferences.palette, setPalette]);

    useEffect(() => {
        setApiLocales({data: preferences.dataLocale});
    }, [preferences.dataLocale]);

    if (!loaded || (authenticated && !profilesReady)) {
        return (
            <FullPageLoader
                label={t('preferences.loading', 'Loading user preferences…')}
            />
        );
    }

    return <>{children}</>;
}
