import {createContext} from 'react';

export type TOverlayRouterContext = {
    close: () => void;
};

const contexts: Record<string, TOverlayRouterContext> = {};

export function getOverlayContext(name: string): TOverlayRouterContext {
    if (contexts[name]) {
        return contexts[name];
    }

    return contexts[name] = createContext<TOverlayRouterContext | undefined>(undefined);
}
