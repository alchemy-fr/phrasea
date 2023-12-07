import React, {PropsWithChildren} from 'react';
import {
    TUserPreferencesContext,
    UpdatePreferenceHandler,
    UserPreferences,
    UserPreferencesContext,
} from './UserPreferencesContext';
import {getUserPreferences, putUserPreferences} from '../../../api/user';
import {createCachedTheme} from '../../../lib/theme';
import {CssBaseline, GlobalStyles, ThemeProvider} from '@mui/material';
import {useAuth} from "@alchemy/auth/react";

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
    const [preferences, setPreferences] = React.useState<UserPreferences>(
        getFromStorage()
    );
    const {tokens} = useAuth();

    const updatePreference = React.useCallback<UpdatePreferenceHandler>(
        (name, value) => {
            setPreferences(prev => {
                const newPrefs = {...prev};

                if (typeof value === 'function') {
                    newPrefs[name] = value(newPrefs[name]);
                } else {
                    newPrefs[name] = value;
                }

                if (tokens) {
                    putUserPreferences(name, newPrefs[name]);
                }

                sessionStorage.setItem(
                    sessionStorageKey,
                    JSON.stringify(newPrefs)
                );

                return newPrefs;
            });
        },
        [tokens]
    );

    React.useEffect(() => {
        if (tokens) {
            getUserPreferences().then(r =>
                setPreferences({
                    ...r,
                })
            );
        }
    }, [tokens]);

    const value = React.useMemo<TUserPreferencesContext>(() => {
        return {
            preferences,
            updatePreference,
        };
    }, [preferences, updatePreference]);

    const scrollbarWidth = 8;

    return (
        <UserPreferencesContext.Provider value={value}>
            <ThemeProvider
                theme={createCachedTheme(preferences.theme ?? 'default')}
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
                            backgroundColor: theme.palette.common.white,
                        },
                    })}
                />
                {children}
            </ThemeProvider>
        </UserPreferencesContext.Provider>
    );
}
