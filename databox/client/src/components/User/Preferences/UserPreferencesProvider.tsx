import React, {PropsWithChildren} from 'react';
import {createCachedThemeOptions} from '@alchemy/phrasea-framework';
import {ThemeEditorProvider} from '@alchemy/phrasea-framework';
import {Classes} from '../../../classes.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {AppGlobalTheme} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';
import {useUserPreferencesStore} from '../../../store/userPreferencesStore.ts';
import {useAuth} from '@alchemy/react-auth';
import {updateClientDataLocale} from '../../../store/useDataLocaleStore.ts';
import {scrollbarWidth} from '../../uiVars.ts';

type Props = PropsWithChildren<{}>;

export default function UserPreferencesProvider({children}: Props) {
    const {t} = useTranslation();
    const {user, isAuthenticated, hasSession} = useAuth();
    const loadingRef = React.useRef(false);

    const preferences = useUserPreferencesStore(s => s.preferences);
    const loadPreferences = useUserPreferencesStore(s => s.load);
    const loading = useUserPreferencesStore(s => s.loading);

    if (hasSession && !isAuthenticated) {
        loadingRef.current = true;
    }

    const isLoading = loading || loadingRef.current;

    React.useEffect(() => {
        if (user) {
            loadingRef.current = false;
            loadPreferences();
        }
    }, [loadPreferences, user]);

    React.useEffect(() => {
        updateClientDataLocale(preferences?.dataLocale);
    }, [preferences?.dataLocale]);

    return (
        <ThemeEditorProvider
            defaultTheme={createCachedThemeOptions(
                preferences.theme ?? 'default'
            )}
        >
            <AppGlobalTheme
                scrollbarWidth={scrollbarWidth}
                styles={() => ({
                    [`.${Classes.ellipsisText} .MuiListItemText-secondary`]: {
                        textOverflow: 'ellipsis',
                        wordBreak: 'break-all',
                        overflow: 'hidden',
                        whiteSpace: 'nowrap',
                    },
                })}
            >
                {!isLoading ? (
                    children
                ) : (
                    <FullPageLoader
                        backdrop={false}
                        message={t(
                            'user_preferences.loading',
                            'Loading user preferences…'
                        )}
                    />
                )}
            </AppGlobalTheme>
        </ThemeEditorProvider>
    );
}
