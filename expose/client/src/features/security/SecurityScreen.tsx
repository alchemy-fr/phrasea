'use client';

import {type FormEvent, type ReactNode, useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {LockIcon, ShieldXIcon, UserRoundIcon} from 'lucide-react';
import {
    AuthorizationError,
    SecurityMethod,
    type Publication,
} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {FieldError, Input, Label} from '@alchemy/next-ui/ui/input';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {useAuth} from '@/lib/auth/AuthProvider';
import {hasLoggedOut} from '@/lib/auth/loggedOut';
import {isInIframe} from '@/lib/auth/loginWindow';
import {storePassword} from '@/lib/api/passwords';
import {ErrorScreen} from '@/components/ErrorScreen';

type Props = {
    /** The publication, or the summary of the publication of an asset */
    publication: Publication;
    /** Loads the content again, once the user did what was asked */
    reload: () => Promise<unknown>;
};

/**
 * What a visitor without access to a publication must do: type its
 * password, or sign in with an account allowed to see it.
 */
export function SecurityScreen({publication, reload}: Props) {
    switch (publication.securityMethod) {
        case SecurityMethod.Password:
            return <PasswordMethod publication={publication} reload={reload} />;
        case SecurityMethod.Authentication:
            return (
                <AuthenticationMethod
                    publication={publication}
                    reload={reload}
                />
            );
        default:
            return <ErrorScreen error={undefined} onRetry={() => reload()} />;
    }
}

function SecurityCard({
    icon,
    title,
    children,
    testId,
}: {
    icon: ReactNode;
    title: ReactNode;
    children: ReactNode;
    testId: string;
}) {
    return (
        <div className="flex min-h-[70dvh] items-center justify-center p-4">
            <div
                data-testid={testId}
                className="w-full max-w-sm rounded-xl border bg-card p-6 text-card-foreground shadow-sm"
            >
                <div className="mb-4 flex flex-col items-center gap-3 text-center">
                    <span className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary [&>svg]:size-6">
                        {icon}
                    </span>
                    <h1 className="text-lg font-semibold">{title}</h1>
                </div>
                {children}
            </div>
        </div>
    );
}

function passwordErrorMessage(
    error: string | null | undefined,
    t: ReturnType<typeof useTranslation>['t']
): string | undefined {
    switch (error) {
        case undefined:
        case null:
        case AuthorizationError.MissingPassword:
            return undefined;
        case AuthorizationError.InvalidPassword:
            return t('security.password.invalid', 'Invalid password');
        case AuthorizationError.TooManyAttempts:
            return t(
                'security.password.too_many_attempts',
                'Too many wrong passwords. Please try again in a few minutes.'
            );
        default:
            return error;
    }
}

function PasswordMethod({publication, reload}: Props) {
    const {t} = useTranslation();
    const [password, setPassword] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const error = passwordErrorMessage(publication.authorizationError, t);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        if (!password || !publication.securityContainerId) {
            return;
        }
        setSubmitting(true);
        storePassword(publication.securityContainerId, password);
        try {
            await reload();
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <SecurityCard
            testId="password-method"
            icon={<LockIcon />}
            title={t('security.password.title', 'Protected publication')}
        >
            <form onSubmit={submit} className="space-y-4">
                <p className="text-center text-sm text-muted-foreground">
                    {t(
                        'security.password.description',
                        'This publication is protected by a password. Please type it to continue.'
                    )}
                </p>
                <div>
                    <Label htmlFor="publication-password" className="mb-1.5">
                        {t('security.password.label', 'Password')}
                    </Label>
                    <Input
                        id="publication-password"
                        type="password"
                        autoFocus
                        autoComplete="current-password"
                        value={password}
                        aria-invalid={!!error}
                        onChange={e => setPassword(e.target.value)}
                    />
                    <FieldError>{error}</FieldError>
                </div>
                <Button
                    type="submit"
                    className="w-full"
                    loading={submitting}
                    disabled={!password}
                >
                    {t('security.password.submit', 'Enter')}
                </Button>
            </form>
        </SecurityCard>
    );
}

function AuthenticationMethod({publication, reload}: Props) {
    const {t} = useTranslation();
    const {
        status,
        user,
        login,
        loginInWindow,
        logout,
        loggingOut,
        redirecting,
    } = useAuth();
    const queryClient = useQueryClient();
    const [embedded] = useState(isInIframe);
    const [loggedOut] = useState(hasLoggedOut);
    const [opening, setOpening] = useState(false);
    // Straight to Keycloak, unless the page is framed (Keycloak cannot be)
    // or the user just signed out
    const autoLogin =
        status === 'anonymous' && !embedded && !loggedOut && !loggingOut;

    useEffect(() => {
        if (autoLogin) {
            login();
        }
    }, [autoLogin, login]);

    if (status === 'loading' || autoLogin || loggingOut) {
        return <FullPageLoader />;
    }

    if (status === 'authenticated') {
        // Signed in, still no access: the account is not allowed — signing
        // in again would only come back here
        const notAllowed =
            publication.authorizationError === AuthorizationError.NotAllowed;

        return (
            <SecurityCard
                testId="authentication-not-allowed"
                icon={<ShieldXIcon />}
                title={t(
                    'security.authentication.not_allowed.title',
                    'Access denied'
                )}
            >
                <p className="mb-4 text-center text-sm text-muted-foreground">
                    {notAllowed
                        ? t(
                              'security.authentication.not_allowed.description',
                              'Sorry! You are not allowed to access this publication with the account {{name}}.',
                              {name: user?.username}
                          )
                        : publication.authorizationError}
                </p>
                <div className="flex flex-col gap-2">
                    <Button variant="outline" onClick={() => reload()}>
                        {t('error.retry', 'Retry')}
                    </Button>
                    {embedded ? null : (
                        <Button variant="ghost" onClick={logout}>
                            {t(
                                'security.authentication.switch_account',
                                'Sign in with another account'
                            )}
                        </Button>
                    )}
                </div>
            </SecurityCard>
        );
    }

    return (
        <SecurityCard
            testId="authentication-method"
            icon={<UserRoundIcon />}
            title={t('security.authentication.title', 'Sign in required')}
        >
            <p className="mb-4 text-center text-sm text-muted-foreground">
                {t(
                    'security.authentication.description',
                    'This publication requires authentication.'
                )}
            </p>
            <Button
                className="w-full"
                data-testid="authentication-sign-in"
                loading={redirecting || opening}
                onClick={async () => {
                    if (!embedded) {
                        login();

                        return;
                    }
                    setOpening(true);
                    try {
                        if (await loginInWindow()) {
                            await queryClient.invalidateQueries();
                        }
                    } finally {
                        setOpening(false);
                    }
                }}
            >
                {t('auth.sign_in', 'Sign in')}
            </Button>
        </SecurityCard>
    );
}
