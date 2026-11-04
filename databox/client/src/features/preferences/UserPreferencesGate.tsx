'use client';

import {PropsWithChildren, useEffect, useRef, useState} from 'react';
import {useTheme} from 'next-themes';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@/lib/auth/AuthProvider';
import {usePreferencesStore} from './store';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {setApiLocales} from '@/lib/api/http';
import {useThemeStore} from '@alchemy/next-theme/themeStore';
import {useProfileStore} from '@/features/profiles/profileStore';
import {hydrateDefinitions} from '@/features/attributes/definitionsCache';

/**
 * Loads server-side user preferences once the session is known, then applies
 * the persisted theme / data locale before rendering the app, and loads the
 * display profile they select (the attribute lists, grid cards and default
 * search filters follow it) before the first search. The attribute
 * definitions cached in the tab are restored first too, so that the first
 * render already shows their labels.
 */
export function UserPreferencesGate({children}: PropsWithChildren) {
    const {status, user} = useAuth();
    const {loaded, load, preferences, authenticated} = usePreferencesStore();
    const {setTheme} = useTheme();
    const {t, i18n} = useTranslation();

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

    // Called again when the language or the user changes without reload:
    // the labels and the visible definitions depend on them. Only the first
    // hydration holds the app back.
    const definitionsUser = user?.id ?? 'anonymous';
    const language = i18n.language;
    const [definitionsHydrated, setDefinitionsHydrated] = useState(false);
    useEffect(() => {
        if (loaded) {
            void hydrateDefinitions({user: definitionsUser, locale: language});
            setDefinitionsHydrated(true);
        }
    }, [loaded, definitionsUser, language]);

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
            setPalette(preferences.palette);
        }
    }, [preferences.palette, setPalette]);

    useEffect(() => {
        setApiLocales({data: preferences.dataLocale});
    }, [preferences.dataLocale]);

    if (!loaded || !definitionsHydrated || (authenticated && !profilesReady)) {
        return (
            <FullPageLoader
                label={t('preferences.loading', 'Loading user preferences…')}
            />
        );
    }

    return <>{children}</>;
}
