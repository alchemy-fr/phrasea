import {useState} from 'react';
import {AxiosError} from 'axios';
import {UseFormProps} from 'react-hook-form/dist/types/form';
import {
    ApiErrorMapping,
    mapApiErrors,
    normalizeApiPlatformPath,
    NormalizePath,
} from '../lib/form';
import {useForm} from 'react-hook-form';
import {FieldValues} from 'react-hook-form/dist/types/fields';
import {toast} from 'react-toastify';

type OnBeforeSubmit<T extends FieldValues> = (
    data: T,
    next: () => Promise<void>,
    abort: () => void,
) => void;
type OnSubmit<T extends FieldValues, R> = (data: T) => Promise<R>;

type Props<T extends FieldValues, R> = {
    toastSuccess?: string;
    onBeforeSubmit?: OnBeforeSubmit<T>;
    onSubmit: OnSubmit<T, R>;
    onSuccess?: (res: R) => void;
    apiErrors?: {
        mapping?: ApiErrorMapping<T>;
        normalizePath?: NormalizePath;
    };
} & UseFormProps;

export default function useFormSubmit<T extends FieldValues, R = any>({
    onBeforeSubmit,
    onSubmit,
    onSuccess,
    apiErrors,
    toastSuccess,
    ...useFormProps
}: Props<T, R>) {
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [remoteErrors, setRemoteErrors] = useState<string[]>([]);

    const useFormResponse = useForm<T>(useFormProps as UseFormProps<T>);

    const {handleSubmit, setError, getValues} = useFormResponse;

    const doSubmit = async (data: T): Promise<void> => {
        try {
            setRemoteErrors([]);
            const res: R = await onSubmit(data);
            setSubmitted(true);
            setSubmitting(false);
            if (toastSuccess) {
                toast.success(toastSuccess);
            }
            onSuccess && onSuccess(res);
        } catch (e: any) {
            console.log('error', e);
            if (e.isAxiosError) {
                const err = e as AxiosError;
                if (422 === err.response?.status) {
                    mapApiErrors(
                        err,
                        setError,
                        setRemoteErrors,
                        getValues,
                        apiErrors?.mapping,
                        apiErrors?.normalizePath || normalizeApiPlatformPath,
                    );
                } else if (
                    err.response &&
                    [400, 500].includes(err.response.status)
                ) {
                    setRemoteErrors(p =>
                        p.concat(
                            err.response!.data['hydra:description'] as string,
                        ),
                    );
                }
            }
            setSubmitting(false);
        }
    };

    const submitHandler = async (data: T): Promise<void> => {
        setSubmitting(true);
        if (onBeforeSubmit) {
            onBeforeSubmit(
                data,
                async () => {
                    await doSubmit(data);
                },
                () => {
                    setSubmitting(false);
                },
            );

            return;
        }

        await doSubmit(data);
    };

    const setOnSubmit = (fn: OnSubmit<T, R>) => {
        onSubmit = fn;
    };

    const forbidNavigation =
        useFormResponse.formState.isDirty && !submitted && !submitting;

    return {
        ...useFormResponse,
        handleSubmit: handleSubmit(submitHandler),
        setOnSubmit,
        remoteErrors,
        submitting,
        submitted,
        forbidNavigation,
    };
}
