import {
    DefaultValues,
    FieldValues,
    UseFormProps,
    UseFormReturn,
} from 'react-hook-form';
import {AxiosError, AxiosInstance} from 'axios';
import {type IAxiosRetryConfigExtended} from 'axios-retry';
import {BaseSyntheticEvent} from 'react';
import {ApiErrorMapping, NormalizePath} from './form';

export type RequestMeta = {
    requestStartedAt?: number;
    responseTime?: number;
};

declare module 'axios' {
    export interface AxiosRequestConfig extends IAxiosRetryConfigExtended {
        meta?: RequestMeta;
        errorHandled?: boolean;
        handledErrorStatuses?: number[];
    }
}

export type OnBeforeSubmit<T extends FieldValues> = (
    data: T,
    next: () => Promise<void>,
    abort: () => void
) => void;

export type OnSubmit<T extends FieldValues, R> = (data: T) => Promise<R>;
export type SetOnSubmit<T extends FieldValues, R = T> = (
    fn: OnSubmit<T, R>
) => void;
export type RemoteErrors = string[];

export type UseFormSubmitProps<
    T extends FieldValues,
    R = T,
    FormData extends FieldValues = T,
> = {
    normalize?: (data: T) => DefaultValues<FormData>;
    denormalize?: (data: FormData) => T;
    toastSuccess?: string;
    onBeforeSubmit?: OnBeforeSubmit<FormData>;
    onSubmit: OnSubmit<T, R>;
    onSuccess?: (res: R) => void;
    onError?: (errors: string) => void;
    apiErrors?: {
        mapping?: ApiErrorMapping<FormData>;
        normalizePath?: NormalizePath;
    };
} & UseFormProps<FormData>;

export type UseFormSubmitReturn<
    T extends FieldValues,
    R = T,
    FormData extends FieldValues = T,
> = {
    handleSubmit: (e?: BaseSyntheticEvent) => Promise<void>;
    setOnSubmit: SetOnSubmit<T, R>;
    remoteErrors: RemoteErrors;
    submitting: boolean;
    submitted: boolean;
    isDirty: boolean;
    forbidNavigation: boolean;
} & Omit<UseFormReturn<FormData>, 'handleSubmit'>;

export type SimpleAxiosError<Data = any> = {
    code: number;
    message: string;
    error: AxiosError;
    data: Data | undefined;
};
export type ErrorListener = (error: AxiosError) => void;

export type HttpClient = {
    errorListeners: ErrorListener[];
    addErrorListener: (listener: ErrorListener) => void;
    removeErrorListener: (listener: ErrorListener) => void;
    setApiLocale: (locale: string) => void;
} & AxiosInstance;

export type MultipartUpload = {
    uploadId: string;
    parts: UploadPart[];
};
export type UploadPart = {
    ETag: string;
    PartNumber: number;
};

export interface ApiHydraObjectResponse {
    '@id': string;
    '@type': string;
}

export type NormalizedCollectionResponse<T, E extends {} = {}> = {
    total: number;
    first?: string | null;
    previous?: string | null;
    next?: string | null;
    last?: string | null;
    result: T[];
} & E;

export type HydraCollectionResponse<T, E extends {} = {}> = {
    'hydra:totalItems': number;
    'hydra:view'?: {
        'hydra:first': string;
        'hydra:previous': string;
        'hydra:next': string;
        'hydra:last': string;
    };
    'hydra:member': T[];
} & E;

export enum ApiConstant {
    HydraTitle = 'hydra:title',
    HydraDescription = 'hydra:description',
    UnknownError = 'Unknown error',
}
