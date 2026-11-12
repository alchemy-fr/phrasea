'use client';

import {useTranslation} from 'react-i18next';
import {LogInIcon} from 'lucide-react';
import {useState} from 'react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Avatar} from '@alchemy/next-ui/ui/misc';
import {Tooltip} from '@alchemy/next-ui/ui/overlays';
import {useAuth} from '@/lib/auth/AuthProvider';

/**
 * Who is signed in, in the services menu (which has no top bar): the
 * session is usually picked up silently from Keycloak, otherwise the user
 * signs in from a window, Keycloak refusing to be framed. Signing out is
 * left to the client framing the menu.
 */
export function EmbeddedAccount() {
    const {t} = useTranslation();
    const {status, user, loginInWindow} = useAuth();
    const [pending, setPending] = useState(false);

    if (status === 'loading') {
        return null;
    }

    if (!user) {
        return (
            <Button
                size="sm"
                variant="outline"
                data-testid="embedded-sign-in"
                loading={pending}
                onClick={() => {
                    setPending(true);
                    void loginInWindow().finally(() => setPending(false));
                }}
            >
                <LogInIcon /> {t('user.login', 'Sign in')}
            </Button>
        );
    }

    const name = user.name ?? user.username;

    return (
        <Tooltip content={user.email ?? name}>
            <span
                data-testid="embedded-user"
                className="flex min-w-0 items-center gap-2 text-sm"
            >
                <Avatar name={name} size="sm" />
                <span className="truncate">{name}</span>
            </span>
        </Tooltip>
    );
}
