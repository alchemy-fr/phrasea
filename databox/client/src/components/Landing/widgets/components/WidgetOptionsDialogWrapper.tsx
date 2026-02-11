import WidgetOptionsContainer from './WidgetOptionsContainer.tsx';
import {useCallback, useState} from 'react';
import {WidgetOptionsDialogWrapperProps} from '../widgetTypes.ts';
import IconButton from '@mui/material/IconButton';
import SettingsIcon from '@mui/icons-material/Settings';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {Button} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import {useModals} from '@alchemy/navigation';
import {ConfirmDialog} from '@alchemy/phrasea-framework';

export default function WidgetOptionsDialogWrapper({
    children,
    dialogProps = {},
    ...props
}: WidgetOptionsDialogWrapperProps) {
    const {openModal} = useModals();
    const {title} = props;
    const {t} = useTranslation();

    const {onRemove} = props;
    const [open, setOpen] = useState(false);

    const onDelete = useCallback(() => {
        openModal(ConfirmDialog, {
            title: t('editor.widgets.remove_widget', 'Remove widget'),
            onConfirm: async () => {
                onRemove();
            },
        });
    }, [openModal, onRemove]);

    return (
        <WidgetOptionsContainer {...props}>
            <IconButton onClick={() => setOpen(true)}>
                <SettingsIcon />
            </IconButton>
            <IconButton onClick={() => onDelete()}>
                <DeleteIcon />
            </IconButton>
            {open ? (
                <AppDialog
                    open={true}
                    title={title}
                    onClose={() => setOpen(false)}
                    actions={({onClose}) => (
                        <>
                            <Button onClick={() => onClose()}>
                                {t('common.close', 'Close')}
                            </Button>
                        </>
                    )}
                    {...dialogProps}
                >
                    <div>{children}</div>
                </AppDialog>
            ) : null}
        </WidgetOptionsContainer>
    );
}
