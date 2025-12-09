import React, {PropsWithChildren} from 'react';
import {createCachedThemeOptions} from '../../../lib/theme';
import {CssBaseline, GlobalStyles} from '@mui/material';
import {ThemeEditorProvider} from '@alchemy/theme-editor';
import {Classes} from '../../../classes.ts';
import {scrollbarWidth} from '../../../constants.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {useUserPreferencesStore} from '../../../store/userPreferencesStore.ts';
import {useAuth} from '@alchemy/react-auth';
import {useAttributeListStore} from '../../../store/attributeListStore.ts';
import {updateClientDataLocale} from '../../../store/useDataLocaleStore.ts';

type Props = PropsWithChildren<{}>;

export default function UserPreferencesProvider({children}: Props) {
    const {t} = useTranslation();
    const {user, isAuthenticated, hasSession} = useAuth();
    const loadingRef = React.useRef(false);

    const preferences = useUserPreferencesStore(s => s.preferences);
    const loadPreferences = useUserPreferencesStore(s => s.load);
    const loading = useUserPreferencesStore(s => s.loading);
    const setCurrentAttrList = useAttributeListStore(s => s.setCurrent);

    if (hasSession && !isAuthenticated) {
        loadingRef.current = true;
    }

    const isLoading = loading || loadingRef.current;

    React.useEffect(() => {
        if (user) {
            loadingRef.current = false;
            loadPreferences().then(up => {
                if (up.attrList) {
                    setCurrentAttrList(up.attrList);
                }
            });
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
            <CssBaseline />
            <GlobalStyles
                styles={theme => ({
                    '*': {
                        '*::-webkit-scrollbar': {
                            width: scrollbarWidth,
                        },
                        '*::-webkit-scrollbar-track': {
                            borderRadius: 10,
                        },
                        '*::-webkit-scrollbar-thumb': {
                            borderRadius: scrollbarWidth,
                            backgroundColor: theme.palette.primary.main,
                        },
                    },
                    'body': {
                        backgroundColor: theme.palette.background.default,
                    },
                    [`.${Classes.ellipsisText} .MuiListItemText-secondary`]: {
                        textOverflow: 'ellipsis',
                        wordBreak: 'break-all',
                        overflow: 'hidden',
                        whiteSpace: 'nowrap',
                    },
                })}
            />

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
        </ThemeEditorProvider>
    );
}
