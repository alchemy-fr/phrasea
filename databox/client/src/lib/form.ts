import {AxiosError} from "axios";
import {UseFormSetError} from "react-hook-form/dist/types/form";
import {FieldValues} from "react-hook-form/dist/types/fields";
import {Path} from "react-hook-form";

type Violation = {
    code: number | null;
    propertyPath: string;
    message: string;
}

export function mapApiErrors<TFieldValues extends FieldValues>(
    error: AxiosError<any>,
    setError: UseFormSetError<TFieldValues>,
    mapping: Record<string, Path<TFieldValues>> = {}
): void {
    const res = error.response;
    if (res?.status !== 422) {
        return;
    }

    if (res.data) {
        const violations: Violation[] = res.data['violations'] || [];
        violations.forEach(v => {
            setError((mapping[v.propertyPath] || v.propertyPath) as Path<TFieldValues>, {
                message: v.message,
            })
        });
    }
}
