import {getAuthClient} from './client';
import type {Tokens} from '@alchemy/next-core/auth/oidc';

/** Page of the sign-in window: it signs in, then hands the tokens over */
export const LOGIN_WINDOW_PATH = '/auth/window';

const MESSAGE_TYPE = 'dashboard:auth-tokens';

type TokensMessage = {type: typeof MESSAGE_TYPE; tokens: Tokens};

function isTokensMessage(data: unknown): data is TokensMessage {
    return (
        typeof data === 'object' &&
        data !== null &&
        (data as {type?: unknown}).type === MESSAGE_TYPE &&
        typeof (data as {tokens?: {accessToken?: unknown}}).tokens
            ?.accessToken === 'string'
    );
}

/**
 * Opens the sign-in in a separate window: Keycloak cannot be framed, and an
 * embedded page has no storage in common with a top-level window (storage
 * partitioning), so the window posts the tokens back once signed in.
 */
export function openLoginWindow(): Promise<boolean> {
    const popup = window.open(
        LOGIN_WINDOW_PATH,
        'dashboard-login',
        'popup,width=600,height=720'
    );
    if (!popup) {
        return Promise.resolve(false);
    }

    return new Promise(resolve => {
        const done = (result: boolean) => {
            window.removeEventListener('message', onMessage);
            clearInterval(closedPoll);
            resolve(result);
        };
        const onMessage = (event: MessageEvent) => {
            if (
                event.origin !== window.location.origin ||
                event.source !== popup ||
                !isTokensMessage(event.data)
            ) {
                return;
            }
            getAuthClient().adoptTokens(event.data.tokens);
            done(true);
        };
        const closedPoll = setInterval(() => {
            if (popup.closed) {
                done(false);
            }
        }, 500);
        window.addEventListener('message', onMessage);
    });
}

/**
 * Called by the sign-in window once signed in: hands the tokens over to the
 * page that opened it, then closes. Returns false when there is no such page.
 */
export function handOverTokens(): boolean {
    const tokens = getAuthClient().getTokens();
    const opener = window.opener as Window | null;
    if (!tokens || !opener || opener.closed) {
        return false;
    }
    opener.postMessage(
        {type: MESSAGE_TYPE, tokens} satisfies TokensMessage,
        window.location.origin
    );
    window.close();

    return true;
}
