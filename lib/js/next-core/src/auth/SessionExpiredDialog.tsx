'use client';

import {useTranslation} from 'react-i18next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@alchemy/next-ui/ui/dialog';
import {Button} from '@alchemy/next-ui/ui/button';

/** Offers to sign in again when the session expired */
export function SessionExpiredDialog({
    open,
    redirecting,
    onLogin,
    onDismiss,
}: {
    open: boolean;
    /** The sign in is redirecting to the identity provider */
    redirecting?: boolean;
    onLogin: () => void;
    /** The user stays signed out */
    onDismiss: () => void;
}) {
    const {t} = useTranslation();

    return (
        <Dialog
            open={open}
            onOpenChange={open => {
                if (!open) {
                    onDismiss();
                }
            }}
        >
            <DialogContent data-testid="session-expired">
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            'auth.session_expired.title',
                            'Your session has expired'
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'auth.session_expired.description',
                            'Please sign in again to continue.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        data-testid="session-expired-dismiss"
                        onClick={onDismiss}
                    >
                        {t('auth.stay_signed_out', 'Stay signed out')}
                    </Button>
                    <Button loading={redirecting} onClick={() => onLogin()}>
                        {t('auth.sign_in', 'Sign in')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
