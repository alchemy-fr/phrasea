import {Button, Container, LinearProgress} from '@mui/material';
import {PropsWithChildren, ReactNode} from 'react';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import {useTranslation} from 'react-i18next';

type Props = PropsWithChildren<{
    loading?: boolean;
    onClose: () => void;
    minHeight?: number | undefined;
    disableGutters?: boolean;
    disablePadding?: boolean;
    actions?: ReactNode;
}>;

export default function ContentTab({
    loading,
    children,
    onClose,
    minHeight,
    disableGutters,
    disablePadding,
    actions,
}: Props) {
    const {t} = useTranslation();
    const progressHeight = 3;

    return (
        <>
            <DialogContent dividers>
                <Container
                    sx={{
                        pt: disablePadding ? 0 : 2,
                        m: disablePadding ? -2 : 0,
                        minHeight,
                    }}
                    maxWidth={'lg'}
                    disableGutters={disableGutters}
                >
                    {children}
                </Container>
            </DialogContent>
            {loading && (
                <LinearProgress
                    style={{
                        height: progressHeight,
                        marginBottom: -progressHeight,
                    }}
                />
            )}
            <DialogActions>
                {actions}
                <Button onClick={onClose} disabled={loading}>
                    {t('dialog.close', 'Close')}
                </Button>
            </DialogActions>
        </>
    );
}
