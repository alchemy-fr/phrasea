import {AxiosError} from 'axios';
import {UseFormGetValues, UseFormSetError} from 'react-hook-form';
import {Path, FieldValues} from 'react-hook-form';

type Violation = {
    code: number | null;
    propertyPath: string;
    message: string;
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
    error: AxiosError,
    setError: UseFormSetError<TFieldValues>,
    setRemoteErrors: (handler: (prev: string[]) => string[]) => void,
    getValues?: UseFormGetValues<TFieldValues> | undefined,
    mapping: ApiErrorMapping<TFieldValues> = {},
    normalizePath?: NormalizePath
): void {
    const res = error.response;
    const status = res?.status;

    if (!status || ![422].includes(status)) {
        return;
    }

    const violations: Violation[] = (res!.data as any)['violations'] || [];

    violations.forEach(v => {
        const p1 = normalizePath
            ? normalizePath(v.propertyPath)
            : v.propertyPath;
        const p2 = (mapping[p1] || p1) as Path<TFieldValues>;

        if (getValues && objectHasPropertyPath(getValues(), p2)) {
            setError(p2, {
                message: v.message,
            });
        } else {
            setRemoteErrors(p => p.concat([v.message]));
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
        // eslint-disable-next-line no-prototype-builtins
        if (pointer.hasOwnProperty(parts[i])) {
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
