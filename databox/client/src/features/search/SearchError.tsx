'use client';

import {useTranslation} from 'react-i18next';
import {AlertTriangleIcon, LogInIcon} from 'lucide-react';
import {EmptyState} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {useAuth} from '@/lib/auth/AuthProvider';

export function SearchError({
    error,
    onRetry,
}: {
    error: string;
    onRetry: () => void;
}) {
    const {t} = useTranslation();
    const {status, login, redirecting} = useAuth();
    // Signed out (e.g. the session was lost), the search may refer to fields,
    // workspaces or collections anonymous users cannot see: the API then
    // answers with errors such as `Field "description" not found`.
    const anonymous = status === 'anonymous';

    return (
        <EmptyState
            className="h-full"
            testId="search-error"
            icon={<AlertTriangleIcon className="text-destructive" />}
            title={t('search.error.title', 'Search failed')}
            description={
                <div className="flex flex-col items-center gap-2">
                    {anonymous ? (
                        <p data-testid="search-error-sign-in-hint">
                            {t(
                                'search.error.sign_in_hint',
                                'You are not signed in. This search may use fields, workspaces or collections that are only available to signed-in users: sign in to run it.'
                            )}
                        </p>
                    ) : null}
                    <code className="text-xs break-all">{error}</code>
                </div>
            }
            action={
                <div className="flex flex-wrap justify-center gap-2">
                    <Button variant="outline" onClick={onRetry}>
                        {t('common.retry', 'Retry')}
                    </Button>
                    {anonymous ? (
                        <Button
                            data-testid="search-error-sign-in"
                            loading={redirecting}
                            onClick={() => login()}
                        >
                            <LogInIcon /> {t('user.login', 'Sign in')}
                        </Button>
                    ) : null}
                </div>
            }
        />
    );
}
