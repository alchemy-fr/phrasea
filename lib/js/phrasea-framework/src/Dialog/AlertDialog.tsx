import {PropsWithChildren, ReactNode} from 'react';
import {Box, Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {AppDialog, AppDialogProps} from '@alchemy/phrasea-ui';
import {useModals, StackedModalProps} from '@alchemy/navigation';

type Props = PropsWithChildren<
    {
        onClose?: () => void;
        title?: ReactNode;
        closeLabel?: ReactNode;
        maxWidth?: AppDialogProps['maxWidth'];
        noAction?: boolean | undefined;
    } & StackedModalProps
>;

export default function AlertDialog({
    onClose,
    title,
    maxWidth = 'sm',
    closeLabel,
    open,
    children,
    noAction,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    return (
        <AppDialog
            maxWidth={maxWidth}
            onClose={() => {
                closeModal();
                onClose?.();
            }}
            title={title}
            open={open}
            actions={
                !noAction
                    ? ({onClose}) => (
                          <>
                              <Button variant={'contained'} onClick={onClose}>
                                  {closeLabel || t('dialog.ok', 'OK')}
                              </Button>
                          </>
                      )
                    : undefined
            }
        >
            <Box
                sx={{
                    py: 2,
                }}
            >
                {children}
            </Box>
        </AppDialog>
    );
}
