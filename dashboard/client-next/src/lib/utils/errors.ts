import {toast} from 'sonner';

const fallbackMessage = 'An unexpected error occurred';

export function getErrorMessage(
    e: unknown,
    fallback = fallbackMessage
): string {
    if (e instanceof Error) {
        return e.message || fallback;
    }
    if (typeof e === 'string' && e.trim()) {
        return e;
    }

    return fallback;
}

export function toastError(e: unknown, fallback?: string): void {
    toast.error(getErrorMessage(e, fallback));
}
