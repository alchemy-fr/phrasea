import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
} from '@mui/material';
import {useTranslation} from 'react-i18next';
import React from 'react';
import {useAuth} from '../hooks/useAuth';

type Props = {
    onClose: () => void;
};

export default function SessionAboutToExpireModal({onClose}: Props) {
    const {t} = useTranslation();
    const {logout, refreshToken} = useAuth();
    const [refreshing, setRefreshing] = React.useState(false);

    const handleClose = () => {
        onClose();
    };

    const stay = async () => {
        if (!refreshToken) {
            return;
        }
        setRefreshing(true);
        try {
            await refreshToken();
            handleClose();
        } catch (e: any) {
            if (e.isAxiosError && e.response?.status === 401) {
                logout();
            }
        } finally {
            setRefreshing(false);
        }
    };

    const expired = !refreshToken;

    return (
        <Dialog open={true} keepMounted>
            <DialogTitle>
                {expired
                    ? t(
                          'lib.auth.sess_exp.expired.title',
                          'You session has expired'
                      )
                    : t(
                          'lib.auth.sess_exp.about_to_expire.title',
                          'You session is about to expire'
                      )}
            </DialogTitle>
            <DialogContent>
                <DialogContentText>
                    {expired
                        ? t(
                              'lib.auth.sess_exp.expired.intro',
                              `Don't forget to save your contents before signing in again!`
                          )
                        : t(
                              'lib.auth.sess_exp.about_to_expire.intro',
                              'Are you still here? Do you want to keep your session alive?'
                          )}
                </DialogContentText>
            </DialogContent>
            <DialogActions>
                {expired ? (
                    <>
                        <Button onClick={handleClose}>
                            {t('lib.auth.sess_exp.expired.sign_in', 'Close')}
                        </Button>
                    </>
                ) : (
                    <>
                        <Button
                            disabled={refreshing}
                            onClick={() => {
                                logout();
                                handleClose();
                            }}
                        >
                            {t(
                                'lib.auth.sess_exp.about_to_expire.logout',
                                'Logout'
                            )}
                        </Button>
                        <Button
                            disabled={refreshing}
                            loading={refreshing}
                            onClick={stay}
                        >
                            {t(
                                'lib.auth.sess_exp.about_to_expire.stay',
                                'Keep me in!'
                            )}
                        </Button>
                    </>
                )}
            </DialogActions>
        </Dialog>
    );
}
