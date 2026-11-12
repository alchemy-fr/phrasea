'use client';

import {usePathname, useRouter} from 'next/navigation';
import {SessionExpiredDialog as BaseSessionExpiredDialog} from '@alchemy/next-core/auth/SessionExpiredDialog';
import {useAuth} from './AuthProvider';
import {isUploadLinkPath, routes} from '@/lib/routes';

export function SessionExpiredDialog() {
    const {sessionExpired, login, redirecting, dismissSessionExpired} =
        useAuth();
    const router = useRouter();
    // An upload link does not depend on a session
    const onUploadLink = isUploadLinkPath(usePathname());

    return (
        <BaseSessionExpiredDialog
            open={sessionExpired && !onUploadLink}
            redirecting={redirecting}
            onLogin={() => login()}
            onDismiss={() => {
                // Staying signed out leaves the screen the session expired
                // on — it is most likely forbidden now — for the home page
                dismissSessionExpired();
                router.push(routes.home());
            }}
        />
    );
}
