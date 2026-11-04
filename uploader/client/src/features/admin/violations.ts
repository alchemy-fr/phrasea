import {isApiError} from '@/lib/api/http';

/**
 * The constraint violations of a refused save, by property (`''` for those
 * of the whole object).
 */
export function violationsByField(e: unknown): Record<string, string> {
    if (!isApiError(e)) {
        return {};
    }
    const result: Record<string, string> = {};
    for (const v of e.violations) {
        const field = v.propertyPath ?? '';
        result[field] = result[field]
            ? `${result[field]} ${v.message}`
            : v.message;
    }

    return result;
}
