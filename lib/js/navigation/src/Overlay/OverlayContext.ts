import React, {Context, createContext} from 'react';

export type TOverlayContext = {
    close: () => void;
};

const contexts: Record<string, Context<TOverlayContext | undefined>> = {};

export function getOverlayContext(
    name: string
): Context<TOverlayContext | undefined> {
    if (contexts[name]) {
        return contexts[name];
    }

    return (contexts[name] = createContext<TOverlayContext | undefined>(
        undefined
    ));
}

export function useOverlay(name: string) {
    return React.useContext(getOverlayContext(name));
}
