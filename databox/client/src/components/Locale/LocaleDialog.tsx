import {
    Button,
    List,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    ListSubheader,
    Stack,
} from '@mui/material';
import React from 'react';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {appLocales} from '../../../translations/locales.ts';
import LocaleIcon from './LocaleIcon.tsx';
import {locales} from '@alchemy/i18n/src/Locale/locales';
import {
    useDataLocale,
    useUpdateDataLocale,
} from '../../store/useDataLocaleStore.ts';

type Props = {} & StackedModalProps;

export default function LocaleDialog({open, modalIndex}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const {i18n} = useTranslation();

    const workspaceLocales = Object.keys(locales);

    const dataLocale = useDataLocale();
    const updateDataLocale = useUpdateDataLocale();

    return (
        <AppDialog
            modalIndex={modalIndex}
            open={open}
            title={t('locale.switcher.title', 'Change Language')}
            maxWidth={'xs'}
            onClose={closeModal}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('dialog.close', 'Close')}
                    </Button>
                </>
            )}
            disablePadding
        >
            <Stack
                direction="row"
                gap={2}
                padding={2}
                justifyContent="space-between"
            >
                <div>
                    <List>
                        <ListSubheader>
                            {t(
                                'locale.switcher.app_languages',
                                'App/UI Languages'
                            )}
                        </ListSubheader>
                        {appLocales.map((l: string) => (
                            <ListItemButton
                                key={l}
                                onClick={() => {
                                    i18n.changeLanguage(l);
                                }}
                                selected={i18n.resolvedLanguage === l}
                            >
                                <ListItemIcon>
                                    <LocaleIcon locale={l} height="35" />
                                </ListItemIcon>
                                <ListItemText
                                    primary={t('locale', {
                                        lng: l,
                                        defaultValue: l.toUpperCase(),
                                    })}
                                />
                            </ListItemButton>
                        ))}
                    </List>
                </div>
                <div>
                    <List>
                        <ListSubheader>
                            {t(
                                'locale.switcher.data_languages',
                                'Data Language'
                            )}
                        </ListSubheader>
                        {workspaceLocales.map((l: string) => (
                            <ListItemButton
                                key={l}
                                onClick={() => {
                                    updateDataLocale(l);
                                }}
                                selected={dataLocale === l}
                            >
                                <ListItemIcon>
                                    <LocaleIcon locale={l} height="35" />
                                </ListItemIcon>
                                <ListItemText
                                    primary={
                                        locales[l]?.name || l.toUpperCase()
                                    }
                                    secondary={l}
                                />
                            </ListItemButton>
                        ))}
                    </List>
                </div>
            </Stack>
        </AppDialog>
    );
}
