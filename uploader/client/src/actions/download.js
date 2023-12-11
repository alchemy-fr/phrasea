import apiClient from '../lib/apiClient';

export function Download(url, callback, errCallback) {
    apiClient
        .post('/downloads', {
            url,
        })
        .then(() => callback())
        .catch(errCallback);
}
