'use client';

import type {ReactNode} from 'react';
import type {TFunction} from 'i18next';
import {useTranslation} from 'react-i18next';
import {LockIcon, SearchXIcon, TriangleAlertIcon} from 'lucide-react';
import {isApiError} from '@/lib/api/http';
import {EmptyState} from '@/components/ui/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {Button} from '@/components/ui/button';
import {useCloseRoute} from './RouteDialog';

/** Why an item could not be loaded: missing, not accessible, or failed */
export function loadErrorTitle(t: TFunction, error: unknown): string {
    if (isApiError(error, 404)) {
        return t('load_error.not_found', 'This item does not exist (anymore).');
    }
    if (isApiError(error, 403)) {
        return t(
            'load_error.forbidden',
            'You do not have access to this item.'
        );
    }

    return t('common.error', 'An error occurred');
}

function isKnownLoadError(error: unknown): boolean {
    return isApiError(error, 404) || isApiError(error, 403);
}

/**
 * Shown in place of an item that failed to load. The message of the error is
 * only given when it is neither a 404 nor a 403: the title says it all then.
 */
export function LoadError({
    error,
    action,
    className = 'h-full',
}: {
    error: unknown;
    action?: ReactNode;
    className?: string;
}) {
    const {t} = useTranslation();

    return (
        <EmptyState
            testId="load-error"
            icon={
                isApiError(error, 404) ? (
                    <SearchXIcon />
                ) : isApiError(error, 403) ? (
                    <LockIcon />
                ) : (
                    <TriangleAlertIcon />
                )
            }
            title={loadErrorTitle(t, error)}
            description={
                !isKnownLoadError(error) && error instanceof Error
                    ? error.message
                    : undefined
            }
            action={action}
            className={className}
        />
    );
}

/**
 * Placeholder of a route dialog's content: a loader while its entity loads,
 * the load error — with a button closing the dialog — once it failed.
 */
export function LoadPlaceholder({error}: {error: unknown}) {
    if (!error) {
        return <FullPageLoader />;
    }

    return <ClosableLoadError error={error} />;
}

function ClosableLoadError({error}: {error: unknown}) {
    const {t} = useTranslation();
    const close = useCloseRoute();

    return (
        <LoadError
            error={error}
            action={
                <Button data-testid="load-error-close" onClick={close}>
                    {t('common.close', 'Close')}
                </Button>
            }
        />
    );
}
