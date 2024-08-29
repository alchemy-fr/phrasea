import React, {PropsWithChildren} from 'react';
import {
    TUserPreferencesContext,
    UpdatePreferenceHandler,
    UserPreferences,
    UserPreferencesContext,
} from './UserPreferencesContext';
import {getUserPreferences, putUserPreferences} from '../../../api/user';
import {createCachedThemeOptions} from '../../../lib/theme';
import {CssBaseline, GlobalStyles} from '@mui/material';
import {useAuth} from '@alchemy/react-auth';
import {ThemeEditorProvider} from '@alchemy/theme-editor';
import {Classes} from '../../../classes.ts';
import {scrollbarWidth} from '../../../constants.ts';
import { useTranslation } from 'react-i18next';

const sessionStorageKey = 'userPrefs';

function getFromStorage(): UserPreferences {
    const item = sessionStorage.getItem(sessionStorageKey);

    if (item) {
        return JSON.parse(item);
    }

    return {};
}

type Props = PropsWithChildren<{}>;

export default function UserPreferencesProvider({children}: Props) {
    const {t} = useTranslation();
    const [preferences, setPreferences] =
        React.useState<UserPreferences>(getFromStorage());
    const {user} = useAuth();

    const updatePreference = React.useCallback<UpdatePreferenceHandler>(
        (name, value) => {
            setPreferences(prev => {
                const newPrefs = {...prev};

                if (typeof value === 'function') {
                    newPrefs[name] = value(newPrefs[name]);
                } else {
                    newPrefs[name] = value;
                }

                if (user) {
                    putUserPreferences(name, newPrefs[name]);
                }

                sessionStorage.setItem(
                    sessionStorageKey,
                    JSON.stringify(newPrefs)
                );

                return newPrefs;
            });
        },
        [user]
    );

    React.useEffect(() => {
        if (user) {
            getUserPreferences().then(r =>
                setPreferences({
                    ...r,
                })
            );
        }
    }, [user?.id]);

    const value = React.useMemo<TUserPreferencesContext>(() => {
        return {
            preferences,
            updatePreference,
        };
    }, [preferences, updatePreference]);

    return (
        <UserPreferencesContext.Provider value={value}>
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
                                height: scrollbarWidth,
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
                            backgroundColor: theme.palette.common.white,
                        },
                        [`.${Classes.ellipsisText} .MuiListItemText-secondary`]:
                            {
                                textOverflow: t('user_preferences_provider.ellipsis', `ellipsis`),
                                wordBreak: t('user_preferences_provider.break_all', `break-all`),
                                overflow: t('user_preferences_provider.hidden', `hidden`),
                                whiteSpace: t('user_preferences_provider.nowrap', `nowrap`),
                            },
                    })}
                />
                {children}
            </ThemeEditorProvider>
        </UserPreferencesContext.Provider>
    );
}
