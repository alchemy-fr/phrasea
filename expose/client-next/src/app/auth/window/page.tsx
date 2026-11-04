'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@/lib/auth/AuthProvider';
import {handOverTokens, LOGIN_WINDOW_PATH} from '@/lib/auth/loginWindow';
import {FullPageLoader} from '@/components/ui/loader';
import {EmptyState} from '@/components/ui/misc';

/**
 * The sign-in window opened by an embedded page (see openLoginWindow): it
 * goes through Keycloak, comes back here, then hands the session over to
 * the page that opened it and closes.
 */
export default function LoginWindowPage() {
    const {t} = useTranslation();
    const {status, login} = useAuth();
    const [orphan, setOrphan] = useState(false);

    useEffect(() => {
        if (status === 'anonymous') {
            login(LOGIN_WINDOW_PATH);
        } else if (status === 'authenticated' && !handOverTokens()) {
            setOrphan(true);
        }
    }, [status, login]);

    if (orphan) {
        return (
            <EmptyState
                className="h-[100dvh]"
                title={t('auth.window.done', 'You are signed in')}
                description={t(
                    'auth.window.close',
                    'You can close this window.'
                )}
            />
        );
    }

    return <FullPageLoader label={t('auth.signing_in', 'Signing you in…')} />;
}
