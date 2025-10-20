import React from 'react';
import axios, {AxiosError} from 'axios';
import {useTranslation} from 'react-i18next';
import {hydraDescriptionKey} from './utils';
import {ToastOptions} from 'react-toastify';

type OnError = (message: string, options: ToastOptions) => void;

type Options = {
    onError: OnError;
    logout?: (redirectPathAfterLogin?: string) => void;
};

export default function useRequestErrorHandler({onError, logout}: Options) {
    const {t} = useTranslation();

    return React.useCallback((error: AxiosError<any>) => {
        const config = error.config;
        if (config?.errorHandled || (axios.isCancel(error) as boolean)) {
            return;
        }

        const axiosRetry = config?.['axios-retry'];
        if (axiosRetry && axiosRetry.retries) {
            if ((axiosRetry.retryCount ?? 0) < axiosRetry.retries!) {
                return;
            }
        }

        const status = error.response?.status;
        const data = error.response?.data;

        const handledStatuses = config?.handledErrorStatuses;
        if (
            handledStatuses &&
            handledStatuses.length > 0 &&
            status &&
            handledStatuses.includes(status)
        ) {
            return;
        }

        const defaultOptions: ToastOptions = {
            type: 'error',
        };

        switch (status) {
            case 401:
                onError(
                    t(
                        'lib.api.error.session_expired',
                        'Your session has expired'
                    ) as string,
                    {
                        ...defaultOptions,
                        toastId: 'session_expired',
                    }
                );
                logout &&
                    logout(
                        window.location.href.replace(window.location.origin, '')
                    );
                break;
            case 403:
                onError(
                    t(
                        'lib.api.error.http_unauthorized',
                        'Unauthorized'
                    ) as string,
                    defaultOptions
                );
                break;
            case 400:
                onError(
                    error.response?.data[hydraDescriptionKey] ??
                        t('lib.api.error.http_bad_request', 'Bad Request'),
                    defaultOptions
                );
                break;
            case 404:
                onError(
                    error.response?.data[hydraDescriptionKey] ??
                        t('lib.api.error.http_not_found', 'Not Found'),
                    defaultOptions
                );
                break;
            case 422:
                // Handled by form
                break;
            case 429:
                onError(
                    data?.[hydraDescriptionKey] ||
                        data?.detail ||
                        t('lib.api.http_error.429', {
                            defaultValue:
                                'Too many requests, you can retry in {{minutes}}min',
                            minutes: Math.ceil(
                                parseInt(
                                    error.response?.headers?.['retry-after'] ??
                                        '0'
                                ) / 60
                            ),
                        }),
                    defaultOptions
                );
                break;
            default:
                if (!status) {
                    onError(
                        t('lib.api.error.network', 'Network error') as string,
                        defaultOptions
                    );
                    return;
                } else {
                    onError(
                        t('lib.api.error.http_error', 'Server error') as string,
                        defaultOptions
                    );
                }
                break;
        }
    }, []);
}
