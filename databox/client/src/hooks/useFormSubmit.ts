import {useState} from "react";
import {AxiosError} from "axios";
import {UseFormSetError} from "react-hook-form/dist/types/form";
import {mapApiErrors} from "../lib/form";

type OnSubmit<T extends object, R> = (data: T) => Promise<R>;

type Props<T extends object, R> = {
    onSubmit: OnSubmit<T, R>;
    onSuccess?: (res: R) => void;
}

export default function useFormSubmit<T extends object, R = any>({
                                                                     onSubmit,
                                                                     onSuccess,
                                                                 }: Props<T, R>) {
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);

    const handleSubmit = (setError: UseFormSetError<T>) => async (data: T) => {
        setSubmitting(true);

        try {
            setErrors([]);
            const res: R = await onSubmit(data);
            setSubmitting(false);
            onSuccess && onSuccess(res);
        } catch (e: any) {
            if (e.isAxiosError) {
                const err = e as AxiosError<any>;
                if (422 === err.response?.status) {
                    mapApiErrors(e, setError);
                } else if (err.response && [400, 500].includes(err.response.status)) {
                    setErrors(p => p.concat(err.response!.data['hydra:description'] as string));
                }
            }
            setSubmitting(false);
        }
    }

    return {
        handleSubmit,
        errors,
        submitting,
    };
}