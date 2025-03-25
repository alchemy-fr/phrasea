import axios, {AxiosError} from 'axios';
import type {SimpleAxiosError} from './types';

export const hydraTitleKey = 'hydra:title';
export const hydraDescriptionKey = 'hydra:description';

export function getApiResponseError(e: any): string | undefined {
    if (e.isAxiosError) {
        const status = e.response?.status ?? 0;
        const data = e.response.data;
        if (status === 422 && data.violations) {
            return data.violations
                .map((v: {message: string}) => v.message)
                .join('\n');
        }

        if (data[hydraDescriptionKey]) {
            return `${data[hydraTitleKey]}: ${data[hydraDescriptionKey]}`;
        }

        return getBestErrorProp(data) ?? 'Error';
    }
}

export function getBestErrorProp(data: any): string | undefined {
    if (!data) {
        return;
    }

    if (data[hydraTitleKey] && data[hydraDescriptionKey]) {
        return `${data[hydraTitleKey]}: ${data[hydraDescriptionKey]}`;
    }

    return (
        data['error_message'] ??
        data['detail'] ??
        data['message'] ??
        data[hydraDescriptionKey] ??
        data[hydraTitleKey] ??
        data['title']
    );
}

export function isErrorOfCode(e: any, codes: number[]): e is AxiosError {
    return axios.isAxiosError(e) && codes.includes(e.response?.status ?? 0);
}

export function getAxiosError(error: any): SimpleAxiosError | undefined {
    if (axios.isAxiosError(error)) {
        return {
            error,
            code: error.response?.status ?? 0,
            message:
                getBestErrorProp((error as AxiosError).response?.data) ??
                'Unknown error',
        };
    }
}
