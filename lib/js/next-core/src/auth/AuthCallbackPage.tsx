'use client';

import {Suspense, useEffect, useState} from 'react';
import {useRouter, useSearchParams} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {Button} from '@alchemy/next-ui/ui/button';
import type {OidcClient} from './oidc';

function Callback({getClient}: {getClient: () => OidcClient}) {
    const searchParams = useSearchParams();
    const router = useRouter();
    const {t} = useTranslation();
    const [error, setError] = useState<string>();

    useEffect(() => {
        let cancelled = false;
        getClient()
            .handleCallback(searchParams)
            .then(redirectTo => {
                if (!cancelled) {
                    router.replace(redirectTo);
                }
            })
            .catch(e => {
                if (!cancelled) {
                    setError(e.message ?? String(e));
                }
            });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (error) {
        return (
            <div className="flex h-full flex-col items-center justify-center gap-4 p-8 text-center">
                <h1 className="text-xl font-semibold">
                    {t('auth.error.title', 'Authentication failed')}
                </h1>
                <p className="text-muted-foreground">{error}</p>
                <Button onClick={() => router.replace('/')}>
                    {t('auth.error.back_home', 'Back to home')}
                </Button>
            </div>
        );
    }

    return <FullPageLoader label={t('auth.signing_in', 'Signing you in…')} />;
}

/**
 * The page Keycloak redirects to after the sign in: exchanges the code for
 * the tokens, then goes back to the page the sign in started from.
 */
export function AuthCallbackPage({getClient}: {getClient: () => OidcClient}) {
    return (
        <Suspense fallback={<FullPageLoader />}>
            <Callback getClient={getClient} />
        </Suspense>
    );
}
