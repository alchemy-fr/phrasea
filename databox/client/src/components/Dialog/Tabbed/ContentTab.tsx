import {Button, Container, LinearProgress} from "@mui/material";
import React, {PropsWithChildren} from "react";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import {useTranslation} from 'react-i18next';

type Props<T extends object> = PropsWithChildren<{
    loading?: boolean;
    onClose: () => void;
    minHeight?: number | undefined;
    disableGutters?: boolean;
}>;

export default function ContentTab<T extends object>({
                                                         loading,
                                                         children,
                                                         onClose,
                                                         minHeight,
                                                         disableGutters,
                                                     }: Props<T>) {
    const {t} = useTranslation();
    const progressHeight = 3;

    return <>
        <DialogContent dividers>
            <Container sx={{
                pt: 2,
                minHeight,
            }}
                       maxWidth={'lg'}
                       disableGutters={disableGutters}
            >
                {children}
            </Container>
        </DialogContent>
        {loading && <LinearProgress
            style={{
                height: progressHeight,
                marginBottom: -progressHeight
            }}
        />}
        <DialogActions>
            <Button
                onClick={onClose}
                disabled={loading}
            >
                {t('dialog.close', 'Close')}
            </Button>
        </DialogActions>
    </>
}
