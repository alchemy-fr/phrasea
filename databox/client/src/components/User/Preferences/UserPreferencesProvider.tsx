import React, {PropsWithChildren} from 'react';
import {createCachedThemeOptions} from '../../../lib/theme';
import {CssBaseline, GlobalStyles} from '@mui/material';
import {ThemeEditorProvider} from '@alchemy/theme-editor';
import {Classes} from '../../../classes.ts';
import {scrollbarWidth} from '../../../constants.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {useUserPreferencesStore} from "../../../store/userPreferencesStore.ts";
import {useAuth} from '@alchemy/react-auth';
import {useAttributeListStore} from "../../../store/attributeListStore.ts";

type Props = PropsWithChildren<{}>;

export default function UserPreferencesProvider({children}: Props) {
    const {t} = useTranslation();
    const {user} = useAuth();

    const preferences = useUserPreferencesStore(s => s.preferences);
    const loadPreferences = useUserPreferencesStore(s => s.load);
    const isLoading = useUserPreferencesStore(s => s.isLoading);
    const setCurrentAttrList = useAttributeListStore(s => s.setCurrent);

    React.useEffect(() => {
        if (user) {
            loadPreferences().then(up => {
                if (up.attrList) {
                    setCurrentAttrList(up.attrList);
                }
            });
        }
    }, [loadPreferences, user]);



    return (
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
