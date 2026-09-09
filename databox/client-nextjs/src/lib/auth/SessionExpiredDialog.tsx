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

export function SessionExpiredDialog() {
    const {sessionExpired, login} = useAuth();
    const {t} = useTranslation();

    return (
        <Dialog open={sessionExpired}>
            <DialogContent hideClose>
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
                    <Button onClick={() => login()}>
                        {t('auth.sign_in', 'Sign in')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
