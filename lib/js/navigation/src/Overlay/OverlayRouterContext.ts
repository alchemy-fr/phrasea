import {Context, createContext} from 'react';
import {RouteParameters} from "../types";

export type TOverlayRouterContext = {
    path: string;
    params: RouteParameters;
};

const contexts: Record<string, Context<TOverlayRouterContext | undefined>> = {};

export function getOverlayRouterContext(name: string): Context<TOverlayRouterContext | undefined> {
    if (contexts[name]) {
        return contexts[name];
    }

    return contexts[name] = createContext<TOverlayRouterContext | undefined>(undefined);
}
