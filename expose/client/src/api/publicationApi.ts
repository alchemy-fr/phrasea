import {Publication, UnauthorizedPublication} from '../types.ts';
import {apiClient} from '../init.ts';
import {RawAxiosRequestHeaders} from 'axios';
import {getPasswords} from '../lib/password.ts';

export async function loadPublication(
    id: string
): Promise<Publication | UnauthorizedPublication> {
    return (
        await apiClient.get(`/publications/${id}`, {
            headers: getPasswordHeaders(),
        })
    ).data;
}

export function getPasswordHeaders(): RawAxiosRequestHeaders {
    const headers: RawAxiosRequestHeaders = {};

    const passwords = getPasswords();
    if (passwords) {
        headers['X-Passwords'] = passwords;
    }

    return headers;
}
