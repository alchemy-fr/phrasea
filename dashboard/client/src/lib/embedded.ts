'use client';

import {useSyncExternalStore} from 'react';

/**
 * The dashboard is also the services menu of the other clients, which frame
 * it: it then drops its top bar and sends its links to the whole tab.
 *
 * The attribute is set on <html> by an inline script before the first paint
 * (the server cannot know), so that the compact layout never flashes the
 * full one: the `embedded:` Tailwind variant styles it.
 */
export const EMBEDDED_ATTR = 'data-embedded';

export function isInIframe(): boolean {
    try {
        return window.self !== window.top;
    } catch {
        // A cross-origin parent cannot even be compared
        return true;
    }
}

export const embeddedScript = `(function(){var e;try{e=window.self!==window.top}catch(_){e=true}if(e)document.documentElement.setAttribute('${EMBEDDED_ATTR}','')})()`;

const subscribe = () => () => undefined;

/** Whether the page is framed; false while hydrating */
export function useEmbedded(): boolean {
    return useSyncExternalStore(subscribe, isInIframe, () => false);
}

/**
 * Where a link opens: the current tab, or the tab of the client framing the
 * services menu. A modified click still opens a new tab.
 */
export function linkTarget(embedded: boolean): '_top' | undefined {
    return embedded ? '_top' : undefined;
}
