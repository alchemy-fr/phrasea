import {AxiosError} from 'axios';
import {UseFormGetValues, UseFormSetError} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {Path} from 'react-hook-form';

export type Violation = {
    code: number | null;
    propertyPath: string;
    message: string; // @deprecated
    title: string;
};

export type NormalizePath = (path: string) => string;
export type ApiErrorMapping<TFieldValues extends FieldValues> = Record<
    string,
    Path<TFieldValues>
>;

export function normalizeApiPlatformPath(path: string): string {
    return path.replace(/\[([^\]]+)]/g, '.$1');
}

export function mapApiErrors<TFieldValues extends FieldValues>(
    error: AxiosError<{
        violations?: Violation[];
    }>,
    setError: UseFormSetError<TFieldValues>,
    setRemoteErrors: (handler: (prev: string[]) => string[]) => void,
    getValues: UseFormGetValues<TFieldValues>,
    mapping: ApiErrorMapping<TFieldValues> = {},
    normalizePath?: NormalizePath
): void {
    const res = error.response;
    const status = res?.status;

    if (!status || ![422, 401].includes(status)) {
        return;
    }

    let violations: Violation[] = res!.data['violations'] || [];

    // New API Platform form
    // @ts-expect-error Error
    if (!Array.isArray(violations) && violations.violations) {
        // @ts-expect-error Error
        violations = violations.violations;
    }

    violations.forEach(v => {
        const p1 = normalizePath
            ? normalizePath(v.propertyPath)
            : v.propertyPath;
        const p2 = (mapping[p1] || p1) as Path<TFieldValues>;

        const values = getValues();
        if (objectHasPropertyPath(values, p2)) {
            setError(p2, {
                message: v.title || v.message,
            });
        } else {
            setRemoteErrors(p => p.concat([v.title || v.message]));
        }
    });
}

function objectHasPropertyPath(
    object: Record<string, any>,
    path: string
): boolean {
    const parts = path.split('.');

    let pointer: Record<string, any> = object;
    for (let i = 0; i < parts.length; i++) {
        if (Object.prototype.hasOwnProperty.call(pointer, parts[i])) {
            if (i === parts.length - 1) {
                return true;
            }
            if (typeof pointer[parts[i]] !== 'object') {
                return false;
            }
            pointer = pointer[parts[i]];
        } else {
            return false;
        }
    }

    return false;
}
