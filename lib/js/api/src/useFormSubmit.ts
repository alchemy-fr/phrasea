import {useState} from 'react';
import axios from 'axios';
import {DefaultValues, UseFormProps} from 'react-hook-form';
import {
    ApiErrorMapping,
    mapApiErrors,
    normalizeApiPlatformPath,
    NormalizePath,
} from './form';
import {useForm} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {toast} from 'react-toastify';
import {hydraDescriptionKey} from "./utils";
import {OnBeforeSubmit, OnSubmit, RemoteErrors, SetOnSubmit, UseFormSubmitReturn} from "./types";

type Props<T extends FieldValues, R, FormData extends FieldValues> = {
    normalize?: (data: T) => DefaultValues<FormData>;
    denormalize?: (data: FormData) => T;
    toastSuccess?: string;
    onBeforeSubmit?: OnBeforeSubmit<FormData>;
    onSubmit: OnSubmit<T, R>;
    onSuccess?: (res: R) => void;
    apiErrors?: {
        mapping?: ApiErrorMapping<FormData>;
        normalizePath?: NormalizePath;
    };
} & UseFormProps<FormData>;

export default function useFormSubmit<T extends FieldValues, R = T, FormData extends FieldValues = T>({
    onBeforeSubmit,
    onSubmit,
    onSuccess,
    apiErrors,
    toastSuccess,
    normalize,
    denormalize,
    ...useFormProps
}: Props<T, R, FormData>): UseFormSubmitReturn<T, R, FormData> {
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
            const denormalizedData: T = denormalize ? denormalize(data) : (data as unknown as T);
            const res: R = await onSubmit(denormalizedData);
            setSubmitted(true);
            setSubmitting(false);
            if (toastSuccess) {
                toast.success(toastSuccess);
            }
            onSuccess && onSuccess(res);
        } catch (e: any) {
            console.log('error', e);
            if (axios.isAxiosError<any>(e)) {
                if (422 === e.response?.status) {
                    mapApiErrors(
                        e,
                        setError,
                        setRemoteErrors,
                        getValues,
                        apiErrors?.mapping,
                        apiErrors?.normalizePath || normalizeApiPlatformPath,
                    );
                } else if (
                    e.response &&
                    [400, 500].includes(e.response.status)
                ) {
                    setRemoteErrors(p =>
                        p.concat(
                            e.response!.data[hydraDescriptionKey] as string,
                        ),
                    );
                }
            }
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
                },
            );

            return;
        }

        await doSubmit(data);
    };

    const setOnSubmit: SetOnSubmit<T, R> = (fn) => {
        onSubmit = fn;
    };


    const isDirtyAlt = !!Object.keys(useFormResponse.formState.dirtyFields).length;
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
