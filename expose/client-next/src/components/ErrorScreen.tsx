'use client';

import {useTranslation} from 'react-i18next';
import {
    CircleAlertIcon,
    FileQuestionIcon,
    LockIcon,
    ShieldAlertIcon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {EmptyState} from '@alchemy/next-ui/ui/misc';
import {useAuth} from '@/lib/auth/AuthProvider';
import {isApiError} from '@/lib/api/http';
import {isInIframe} from '@/lib/auth/loginWindow';

/**
 * Full page error of a failed load: what went wrong, and what can be done
 * about it — signing in when the content may be reserved to some users,
 * retrying an unexpected error.
 */
export function ErrorScreen({
    error,
    onRetry,
}: {
    error: unknown;
    onRetry?: () => void;
}) {
    const {t} = useTranslation();
    const {isAuthenticated, login, loginInWindow, redirecting} = useAuth();
    const status = isApiError(error) ? error.status : undefined;
    const signIn =
        !isAuthenticated && (status === 401 || status === 403) ? (
            <Button
                loading={redirecting}
                data-testid="error-sign-in"
                onClick={() => {
                    if (isInIframe()) {
                        void loginInWindow();
                    } else {
                        login();
                    }
                }}
            >
                {t('auth.sign_in', 'Sign in')}
            </Button>
        ) : null;

    switch (status) {
        case 401:
            return (
                <EmptyState
                    testId="error-401"
                    className="min-h-[60dvh]"
                    icon={<LockIcon />}
                    title={t('error.401.title', 'Authentication required')}
                    description={t(
                        'error.401.description',
                        'Sign in to access this page.'
                    )}
                    action={signIn}
                />
            );
        case 403:
            return (
                <EmptyState
                    testId="error-403"
                    className="min-h-[60dvh]"
                    icon={<ShieldAlertIcon />}
                    title={t('error.403.title', 'Forbidden')}
                    description={
                        isAuthenticated
                            ? t(
                                  'error.403.description',
                                  'This content is not available to you: it may be disabled, not open yet or expired.'
                              )
                            : t(
                                  'error.403.description_anonymous',
                                  'This content is not available: it may be disabled, not open yet, expired, or reserved to some users.'
                              )
                    }
                    action={signIn}
                />
            );
        case 404:
            return (
                <EmptyState
                    testId="error-404"
                    className="min-h-[60dvh]"
                    icon={<FileQuestionIcon />}
                    title={t('error.404.title', 'Not found')}
                    description={t(
                        'error.404.description',
                        'This page does not exist, or no longer does.'
                    )}
                />
            );
        default:
            return (
                <EmptyState
                    testId="error-unexpected"
                    className="min-h-[60dvh]"
                    icon={<CircleAlertIcon />}
                    title={t('error.unexpected.title', 'Unexpected error')}
                    description={t(
                        'error.unexpected.description',
                        'Something went wrong. Please try again.'
                    )}
                    action={
                        onRetry ? (
                            <Button variant="outline" onClick={onRetry}>
                                {t('error.retry', 'Retry')}
                            </Button>
                        ) : null
                    }
                />
            );
    }
}
