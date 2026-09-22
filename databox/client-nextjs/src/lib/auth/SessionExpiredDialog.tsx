'use client';

import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
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
import {routes} from '@/lib/routes';

export function SessionExpiredDialog() {
    const {sessionExpired, login, dismissSessionExpired} = useAuth();
    const {t} = useTranslation();
    const router = useRouter();

    /**
     * Staying signed out leaves the screen the session expired on — it is
     * most likely forbidden now — for the home page.
     */
    const staySignedOut = () => {
        dismissSessionExpired();
        router.push(routes.home());
    };

    return (
        <Dialog
            open={sessionExpired}
            onOpenChange={open => {
                if (!open) {
                    staySignedOut();
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
                        onClick={staySignedOut}
                    >
                        {t('auth.stay_signed_out', 'Stay signed out')}
                    </Button>
                    <Button onClick={() => login()}>
                        {t('auth.sign_in', 'Sign in')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
