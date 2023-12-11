import {createContext} from 'react';
import {RouteParameters} from "../types";

export type TOverlayRouteContext = {
    path: string;
    params: RouteParameters;
};


const contexts: Record<string, TOverlayRouteContext> = {};

export function getOverlayRouteContext(name: string): TOverlayRouteContext {
    if (contexts[name]) {
        return contexts[name];
    }

    return contexts[name] = createContext<TOverlayRouteContext | undefined>(undefined);
}
