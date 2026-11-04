import type {ApiError} from '@/lib/api/http';

/** The constraint violations of a 422, by field (first message of each) */
export function violationsByField(error: ApiError): Record<string, string> {
    const errors: Record<string, string> = {};
    for (const {propertyPath, message} of error.violations) {
        const field = propertyPath.split('.')[0] || '_';
        errors[field] ??= message;
    }

    return errors;
}
