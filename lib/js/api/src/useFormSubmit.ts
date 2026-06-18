import {useState} from 'react';
import axios from 'axios';
import {mapApiErrors, normalizeApiPlatformPath} from './form';
import {FieldValues, useForm} from 'react-hook-form';
import {toast} from 'react-toastify';
import {getBestErrorProp} from './utils';
import {
    ApiConstant,
    RemoteErrors,
    SetOnSubmit,
    UseFormSubmitProps,
    UseFormSubmitReturn,
} from './types';

export default function useFormSubmit<
    T extends FieldValues,
    R = T,
    FormData extends FieldValues = T,
>({
    onBeforeSubmit,
    onSubmit,
    onSuccess,
    onError,
    apiErrors,
    toastSuccess,
    normalize,
    denormalize,
    ...useFormProps
}: UseFormSubmitProps<T, R, FormData>): UseFormSubmitReturn<T, R, FormData> {
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [remoteErrors, setRemoteErrors] = useState<RemoteErrors>([]);

    if (normalize) {
        useFormProps.defaultValues = normalize(useFormProps.defaultValues as T);
    }

    const useFormResponse = useForm<FormData>(useFormProps);

    const {handleSubmit, setError, getValues} = useFormResponse;

    const doSubmit = async (data: FormData): Promise<void> => {
        try {
            setRemoteErrors([]);
            const denormalizedData: T = denormalize
                ? denormalize(data)
                : (data as unknown as T);
            const res: R = await onSubmit(denormalizedData);
            setSubmitted(true);
            setSubmitting(false);
            if (toastSuccess) {
                toast.success(toastSuccess);
            }
            onSuccess?.(res);
        } catch (e: any) {
            // eslint-disable-next-line no-console
            console.log('error', e);
            if (axios.isAxiosError<any>(e)) {
                if (422 === e.response?.status) {
                    mapApiErrors(
                        e,
                        setError,
                        setRemoteErrors,
                        getValues,
                        apiErrors?.mapping,
                        apiErrors?.normalizePath ?? normalizeApiPlatformPath
                    );

                    return;
                } else if (
                    e.response &&
                    [400, 500].includes(e.response.status)
                ) {
                    let resData = e.response.data;
                    if (e.response.config.responseType === 'blob') {
                        const txt = await (e.response.data as Blob).text();
                        try {
                            resData = JSON.parse(txt);
                        } catch {
                            resData = {message: txt};
                        }
                    }

                    const newError =
                        getBestErrorProp(resData) ?? ApiConstant.UnknownError;
                    onError?.(newError);

                    setRemoteErrors(p => p.concat(newError));
                }

                throw e;
            }
        } finally {
            setSubmitting(false);
        }
    };

    const submitHandler = async (data: FormData): Promise<void> => {
        setSubmitting(true);

        if (onBeforeSubmit) {
            onBeforeSubmit(
                data,
                async () => {
                    await doSubmit(data);
                },
                () => {
                    setSubmitting(false);
                }
            );

            return;
        }

        await doSubmit(data);
    };

    const setOnSubmit: SetOnSubmit<T, R> = fn => {
        // eslint-disable-next-line react-hooks/immutability
        onSubmit = fn;
    };

    const isDirtyAlt = !!Object.keys(useFormResponse.formState.dirtyFields)
        .length;
    const forbidNavigation = isDirtyAlt && !submitted && !submitting;

    return {
        ...useFormResponse,
        handleSubmit: handleSubmit(submitHandler),
        setOnSubmit,
        remoteErrors,
        submitting,
        submitted,
        isDirty: isDirtyAlt,
        forbidNavigation,
    };
}
