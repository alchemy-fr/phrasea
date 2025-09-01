import {
    Button,
    List,
    ListItemButton,
    ListItemIcon,
    ListItemText,
} from '@mui/material';
import React from 'react';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import i18n from '../../i18n.ts';
import {appLocales} from '../../../translations/locales.ts';
import LocaleIcon from './LocaleIcon.tsx';

type Props = {} & StackedModalProps;

export default function LocaleDialog({open, modalIndex}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

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
            <List>
                {appLocales.map((l: string) => (
                    <ListItemButton
                        key={l}
                        onClick={() => {
                            i18n.changeLanguage(l);
                            closeModal();
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
        </AppDialog>
    );
}
