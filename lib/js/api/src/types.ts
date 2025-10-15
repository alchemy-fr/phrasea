import {FieldValues, UseFormReturn} from 'react-hook-form';
import React from 'react';
import {AxiosError, AxiosInstance} from 'axios';

export type RequestMeta = {
    requestStartedAt?: number;
    responseTime?: number;
};

declare module 'axios' {
    export interface AxiosRequestConfig {
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

export type UseFormSubmitReturn<
    T extends FieldValues,
    R = T,
    FormData extends FieldValues = T,
> = {
    handleSubmit: (e?: React.BaseSyntheticEvent) => Promise<void>;
    setOnSubmit: SetOnSubmit<T, R>;
    remoteErrors: RemoteErrors;
    submitting: boolean;
    submitted: boolean;
    isDirty: boolean;
    forbidNavigation: boolean;
} & Omit<UseFormReturn<FormData>, 'handleSubmit'>;

export type SimpleAxiosError = {
    code: number;
    message: string;
    error: AxiosError;
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
}
export type UploadPart = {
    ETag: string;
    PartNumber: number;
};
