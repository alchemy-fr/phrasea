import {StateParams} from './types';

export function encodeState(params: StateParams): string {
    return btoa(JSON.stringify(params));
}

export function decodeState(state: string | null | undefined): StateParams {
    if (!state) {
        return {};
    }

    try {
        return JSON.parse(atob(state));
    } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);

        return {};
    }
}
