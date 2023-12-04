import React from "react";
import axios, {AxiosError} from "axios";
import {useTranslation} from 'react-i18next';
import {toast} from "react-toastify";
import {hydraDescriptionKey} from "./utils";

type Options = {
    logout?: (redirectPath?: string) => void;
}

export default function useRequestErrorHandler({
    logout
}: Options) {
    const {t} = useTranslation();

    return React.useCallback((error: AxiosError<any>) => {
        if (
            error.config?.errorHandled ||
            (axios.isCancel(error) as boolean)
        ) {
            return;
        }

        const status = error.response?.status;
        const data = error.response?.data;

        switch (status) {
            case 401:
                toast.error(
                    t(
                        'api:error.session_expired',
                        'Your session has expired'
                    ) as string
                );
                logout && logout(window.location.href.replace(window.location.origin, ''));
                break;
            case 403:
                toast.error(
                    t('api:error.http_unauthorized', 'Unauthorized') as string
                );
                break;
            case 400:
                toast.error(
                    error.response?.data[hydraDescriptionKey] as
                        | string
                        | undefined
                );
                break;
            case 404:
                toast.error(
                    error.response?.data[hydraDescriptionKey] as
                        | string
                        | undefined
                );
                break;
            case 422:
                // Handled by form
                break;
            case 429:
                toast.error(data?.[hydraDescriptionKey] || data?.detail || t('api:http_error.429', {
                    defaultValue: 'Too many requests, you can retry in {{minutes}}min',
                    minutes: Math.ceil(parseInt(error.response?.headers?.['retry-after'] ?? '0') / 60),
                }));
                break;
            default:
                toast.error(
                    t('api:error.http_error', 'Server error') as string
                );
                break;
        }
    }, []);
}
