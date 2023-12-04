import useFormSubmit from "./useFormSubmit";
import {FieldValues} from "react-hook-form";

export type RequestMeta = {
    requestStartedAt?: number;
    responseTime?: number;
};

declare module 'axios' {
    export interface AxiosRequestConfig {
        meta?: RequestMeta;
        errorHandled?: boolean;
    }
}

export type UseFormSubmit<T extends FieldValues> = ReturnType<typeof useFormSubmit<T>>
