'use client';

import {useTranslation} from 'react-i18next';
import {UserRoundCogIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {useAuth} from '@/lib/auth/AuthProvider';

/** Constant reminder that the admin is browsing as another user */
export function ImpersonationBanner() {
    const {t} = useTranslation();
    const {user, realUser, impersonating, stopImpersonating} = useAuth();

    if (!impersonating || !user || !realUser) {
        return null;
    }

    return (
        <div
            role="status"
            data-testid="impersonation-banner"
            className="flex shrink-0 items-center justify-center gap-2 bg-warning px-3 py-1 text-sm text-warning-foreground"
        >
            <UserRoundCogIcon className="size-4 shrink-0" />
            <span className="truncate">
                {t('impersonation.banner', 'You are browsing as {{name}}', {
                    name: user.name ?? user.username,
                })}
            </span>
            <Button
                size="sm"
                variant="outline"
                className="h-6 border-warning-foreground/30 bg-transparent px-2 text-xs text-warning-foreground hover:bg-warning-foreground/10"
                onClick={stopImpersonating}
            >
                {t('impersonation.back_to_me', 'Back to my account')}
            </Button>
        </div>
    );
}
