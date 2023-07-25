import {authenticatedRequest} from "../lib/api";

export function Download(url, callback, errCallback) {
    authenticatedRequest({
        url: '/downloads',
        method: 'POST',
        data: {
            url,
        },
    }).then(() => callback());
}
