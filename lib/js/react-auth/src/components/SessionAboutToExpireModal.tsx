import {Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle} from '@mui/material';
import LoadingButton from '@mui/lab/LoadingButton';
import {useTranslation} from 'react-i18next';
import React from "react";
import {useAuth} from "../hooks/useAuth";

export type StayInFunction = () => Promise<void>;

type Props = {
    expiresAt: number | undefined;
    stayIn: StayInFunction;
    onClose: () => void;
};

export default function SessionAboutToExpireModal({
    expiresAt,
    stayIn,
    onClose,
}: Props) {
    const {t} = useTranslation();
    const {logout} = useAuth();
    const [refreshing, setRefreshing] = React.useState(false);

    const handleClose = () => {
        onClose();
    }

    const stay = async () => {
        setRefreshing(true);
        try {
            await stayIn();
            handleClose();
        } catch (e: any) {
            if (e.isAxiosError && e.response?.status === 401) {
                logout();
            }
        } finally {
            setRefreshing(false);
        }
    }

    const expired = undefined === expiresAt;

    return <Dialog
        open={true}
        keepMounted
        onClose={handleClose}
    >
        <DialogTitle>{
            expired ?
                t('auth:session_expiration.dialog.expired.title', 'You session has expired')
                : t('auth:session_expiration.dialog.about_to_expire.title', 'You session is about to expire')
        }</DialogTitle>
        <DialogContent>
            <DialogContentText>
                {
                    expired ?
                        t('auth:session_expiration.dialog.expired.intro', `Don't forget to save your contents before signing in again!`)
                        : t('auth:session_expiration.dialog.about_to_expire.intro', 'Are you still here? Do you want to keep your session alive?')

                }
            </DialogContentText>
        </DialogContent>
        <DialogActions>
            {expired ? <>
                <Button
                    onClick={handleClose}
                >
                    {t('auth:session_expiration.dialog.expired.sign_in', 'Close')}
                </Button>
            </> : <>
                <Button
                    disabled={refreshing}
                    onClick={() => {
                        logout();
                        handleClose();
                    }}
                >
                    {t('auth:session_expiration.dialog.about_to_expire.logout', 'Logout')}
                </Button>
                <LoadingButton
                    disabled={refreshing}
                    loading={refreshing}
                    onClick={stay}>
                    {t('auth:session_expiration.dialog.about_to_expire.stay', 'Keep me in!')}
                </LoadingButton>
            </>
            }

        </DialogActions>
    </Dialog>
}
