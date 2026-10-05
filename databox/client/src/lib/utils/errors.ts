import {toast} from 'sonner';
import {ApiError, isAbortError} from '@/lib/api/http';

const fallbackMessage = 'An unexpected error occurred';

/**
 * Human readable message for anything thrown by the API layer.
 *
 * API Platform constraint violations are appended: the top level message of a
 * 422 is "Validation Failed", which tells the user nothing on its own.
 */
export function getErrorMessage(
    e: unknown,
    fallback = fallbackMessage
): string {
    if (e instanceof ApiError) {
        const violations = e.violations
            .map(v =>
                v.propertyPath ? `${v.propertyPath}: ${v.message}` : v.message
            )
            .filter(Boolean);

        return [e.message || fallback, ...violations].join('\n');
    }
    if (e instanceof Error) {
        return e.message || fallback;
    }
    if (typeof e === 'string' && e.trim()) {
        return e;
    }

    return fallback;
}

/**
 * Reports an error to the user. Aborted requests are silent: they are the
 * result of the user navigating away, not a failure.
 */
export function toastError(e: unknown, fallback?: string): void {
    if (isAbortError(e)) {
        return;
    }
    toast.error(getErrorMessage(e, fallback));
}
