'use client';

import {useTranslation} from 'react-i18next';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useUploaderUser} from './useUploaderUser';

/** Development helper (`DEV_MODE`): what the client knows of the user */
export function UserDebugDialog({open, onOpenChange}: ModalProps) {
    const {t} = useTranslation();
    const {user} = useAuth();
    const {data: me} = useUploaderUser();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="lg">
                <DialogHeader>
                    <DialogTitle>{t('user.debug', 'Debug user')}</DialogTitle>
                </DialogHeader>
                <DialogBody>
                    <pre
                        data-testid="user-debug-content"
                        className="max-h-[60vh] overflow-auto rounded-md bg-muted p-3 text-xs"
                    >
                        {JSON.stringify({token: user, uploader: me}, null, 2)}
                    </pre>
                </DialogBody>
            </DialogContent>
        </Dialog>
    );
}
