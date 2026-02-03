import {
    Button,
    List,
    ListItemButton,
    ListItemIcon,
    ListItemText,
} from '@mui/material';
import React from 'react';
import {useModals} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {LocaleIcon} from './LocaleIcon.tsx';
import ConfirmDialog from '../Dialog/ConfirmDialog.tsx';
import {LocaleDialogProps} from './types';

export default function LocaleDialog({
    open,
    appLocales,
    modalIndex,
}: LocaleDialogProps) {
    const {t, i18n} = useTranslation();
    const {closeModal, openModal} = useModals();

    const confirmReload = (callback: () => Promise<void>) => {
        openModal(ConfirmDialog, {
            title: t(
                'framework.locale.switcher.change_data_locale.modal.title',
                'Page will be reloaded'
            ),
            onConfirm: async () => {
                await callback();
                window.location.reload();
            },
            confirmLabel: t(
                'framework.locale.switcher.change_data_locale.modal.confirm',
                'Continue'
            ),
        });
    };

    const changeLocale = (locale: string | undefined) => {
        i18n.changeLanguage(locale);
    };

    return (
        <AppDialog
            modalIndex={modalIndex}
            open={open}
            title={t('framework.locale.switcher.title', 'Change Language')}
            maxWidth={'xs'}
            onClose={closeModal}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('framework.dialog.close', 'Close')}
                    </Button>
                </>
            )}
            disablePadding
        >
            <List>
                {appLocales.map((l: string) => (
                    <ListItemButton
                        key={l}
                        onClick={() => {
                            changeLocale(l);
                        }}
                        selected={i18n.resolvedLanguage === l}
                    >
                        <ListItemIcon>
                            <LocaleIcon locale={l} height="35" />
                        </ListItemIcon>
                        <ListItemText
                            primary={t('framework.locale.current', {
                                lng: l,
                                defaultValue: 'English',
                            })}
                        />
                    </ListItemButton>
                ))}
            </List>
        </AppDialog>
    );
}
