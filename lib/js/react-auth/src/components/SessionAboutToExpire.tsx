import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import {useTranslation} from 'react-i18next';
import React from "react";
import {useModals, StackedModalProps} from '@alchemy/navigation'
import AuthenticationContext from "../context/AuthenticationContext";

type Props = {
    expiresIn: number;
    cancelLogout: () => void;
} & StackedModalProps;

export default function SessionAboutToExpire({
    open,
    expiresIn,
    cancelLogout,
}: Props) {
    const {t} = useTranslation();
    const {logout, addLogoutListener, removeLogoutListener} = React.useContext(AuthenticationContext);
    const {closeModal} = useModals();
    const closeTimeout = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const [refreshing, setRefreshing] = React.useState(false);

    const clear = () => {
        if (closeTimeout.current) {
            clearTimeout(closeTimeout.current);
            closeTimeout.current = null;
        }
    }

    const handleClose = () => {
        clear();
        closeModal();
    };

    React.useEffect(() => {
        clear();
        closeTimeout.current = setTimeout(handleClose, expiresIn);
    }, []);

    React.useEffect(() => {
        const listener = () => {
            handleClose();
        };
        addLogoutListener(listener);

        return () => {
            removeLogoutListener(listener);
        }
    }, []);

    const stay = async () => {
        cancelLogout();
        clear();
        setRefreshing(true);
        try {
            handleClose();
        } catch (e: any) {
            if (e.isAxiosError && e.response?.status === 401) {
                logout();
            }
        } finally {
            setRefreshing(false);
        }
    }

    return <Dialog
        open={open || false}
        keepMounted
        onClose={handleClose}
    >
        <DialogTitle>{t('auth:session_expiration.dialog.title', 'You session is about to expire')}</DialogTitle>
        <DialogContent>
            <DialogContentText>
                {t('auth:session_expiration.dialog.intro', 'Are you still here? Do you want to keep your session alive?')}
            </DialogContentText>
        </DialogContent>
        <DialogActions>
            <Button
                disabled={refreshing}
                onClick={() => logout()}
            >{t('auth:session_expiration.dialog.logout', 'Logout')}</Button>
            <LoadingButton
                disabled={refreshing}
                loading={refreshing}
                onClick={stay}>{t('auth:session_expiration.dialog.stay', 'Keep me in!')}</LoadingButton>
        </DialogActions>
    </Dialog>
}
