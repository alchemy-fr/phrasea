import {Button, List, ListItemButton} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {themes} from '@alchemy/phrasea-framework';
import type {ThemeName} from '@alchemy/phrasea-framework';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useUserPreferencesStore} from '../../store/userPreferencesStore.ts';
import {AppDialog} from '@alchemy/phrasea-ui';

type Props = {} & StackedModalProps;

export default function ChangeThemeDialog({open}: Props) {
    const {t} = useTranslation();
    const preferences = useUserPreferencesStore(s => s.preferences);
    const updatePreference = useUserPreferencesStore(s => s.updatePreference);

    const {closeModal} = useModals();

    const handleClick = (name: ThemeName) => {
        updatePreference('theme', name);
    };

    const onClose = () => closeModal();

    return (
        <>
            <AppDialog
                maxWidth={'xs'}
                title={t('change_theme.title', 'Choose a theme')}
                onClose={onClose}
                open={open}
                actions={({onClose}) => {
                    return (
                        <Button onClick={onClose}>
                            {t('dialog.close', 'Close')}
                        </Button>
                    );
                }}
                disablePadding={true}
            >
                <List>
                    {(Object.keys(themes) as ThemeName[]).map(
                        (t: ThemeName) => (
                            <ListItemButton
                                selected={preferences.theme === t}
                                onClick={() => handleClick(t)}
                                key={t}
                            >
                                {t}
                            </ListItemButton>
                        )
                    )}
                </List>
            </AppDialog>
        </>
    );
}
