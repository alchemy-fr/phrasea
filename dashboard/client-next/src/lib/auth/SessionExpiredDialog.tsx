'use client';

import {useTranslation} from 'react-i18next';
import {useAuth} from './AuthProvider';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';

/**
 * Offers to sign in again when the session expired. Staying signed out keeps
 * the page: every page is public, the cards tied to roles just go away.
 */
export function SessionExpiredDialog() {
    const {sessionExpired, login, redirecting, dismissSessionExpired} =
        useAuth();
    const {t} = useTranslation();

    return (
        <Dialog
            open={sessionExpired}
            onOpenChange={open => {
                if (!open) {
                    dismissSessionExpired();
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
                        onClick={dismissSessionExpired}
                    >
                        {t('auth.stay_signed_out', 'Stay signed out')}
                    </Button>
                    <Button loading={redirecting} onClick={() => login()}>
                        {t('auth.sign_in', 'Sign in')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
