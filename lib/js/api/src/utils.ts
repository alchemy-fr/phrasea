import axios, {AxiosError} from 'axios';
import {ApiConstant, SimpleAxiosError} from './types';

export function getApiResponseError(e: any): string | undefined {
    const error = getAxiosError(e);

    if (error) {
        const data = e.response.data;
        if (error.code === 422 && data.violations) {
            return data.violations
                .map((v: {message: string}) => v.message)
                .join('\n');
        }

        return error.message;
    }
}

export function getBestErrorProp(data: any): string | undefined {
    if (!data) {
        return;
    }

    if (data[ApiConstant.HydraTitle] && data[ApiConstant.HydraDescription]) {
        return `${data[ApiConstant.HydraTitle]}: ${data[ApiConstant.HydraDescription]}`;
    }

    return (
        data['error_message'] ??
        data['detail'] ??
        data['message'] ??
        data[ApiConstant.HydraDescription] ??
        data[ApiConstant.HydraTitle] ??
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
                ApiConstant.UnknownError,
        };
    }
}
