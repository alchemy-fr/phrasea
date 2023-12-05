import apiClient from '../lib/api';

export function Download(url, callback, errCallback) {
    apiClient
        .post('/downloads', {
            url,
        })
        .then(() => callback())
        .catch(errCallback);
}
