import React, {PropsWithChildren} from 'react';
import {
    TUserPreferencesContext,
    UpdatePreferenceHandler, UpdatePreferenceHandlerArg,
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
import {FullPageLoader} from "@alchemy/phrasea-ui";
import {useTranslation} from 'react-i18next';
import {useMutation, useQuery} from "@tanstack/react-query";
import {queryClient} from "../../../lib/query.ts";

type UpdatePrefVariables<T extends keyof UserPreferences = keyof UserPreferences> = {
    name: T;
    handler: UpdatePreferenceHandlerArg<T>;
};

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
    const {user} = useAuth();
    const {t} = useTranslation();
    const initialData = getFromStorage();
    const queryKey = ['userPreferences', user?.id];

    const {data: preferences, isSuccess} = useQuery<UserPreferences>({
        initialData: initialData,
        queryFn: async () => {
            if (user) {
                return await getUserPreferences();
            }

            return initialData;
        },
        queryKey: queryKey,
    });

    const mutationFn = async <T extends keyof UserPreferences>({
        name,
        handler,
    }: UpdatePrefVariables<T>): Promise<UserPreferences> => {
        return queryClient.setQueryData<UserPreferences>(queryKey, (prev) => {
            const newPrefs = {...(prev ?? {})} as UserPreferences;

            if (typeof handler === 'function') {
                newPrefs[name] = handler(newPrefs[name]);
            } else {
                newPrefs[name] = handler;
            }

            if (user) {
                setTimeout(() => {
                    putUserPreferences(name, newPrefs[name]);
                }, 0);
            }

            sessionStorage.setItem(
                sessionStorageKey,
                JSON.stringify(newPrefs)
            );

            return newPrefs;
        })!;
    };

    const updatePreference = useMutation<UserPreferences, {}, UpdatePrefVariables<any>>({
        mutationFn
    });

    const value = React.useMemo<TUserPreferencesContext>(() => {
        return {
            preferences,
            updatePreference: ((name, handler) => {
                updatePreference.mutate({
                    name,
                    handler,
                });
            }) as UpdatePreferenceHandler,
        };
    }, [preferences, updatePreference]);

    return (
        <UserPreferencesContext.Provider value={value}>
            <ThemeEditorProvider
                defaultTheme={createCachedThemeOptions(
                    preferences.theme ?? 'default'
                )}
            >
                <CssBaseline/>
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
                                textOverflow: 'ellipsis',
                                wordBreak: 'break-all',
                                overflow: 'hidden',
                                whiteSpace: 'nowrap',
                            },
                    })}
                />

                {isSuccess ? children : <FullPageLoader
                    message={t('user_preferences.loading', 'Loading user preferences…')}
                />}
            </ThemeEditorProvider>
        </UserPreferencesContext.Provider>
    );
}
