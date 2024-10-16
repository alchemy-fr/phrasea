import {useLocation, useNavigate, NavigateOptions} from "react-router-dom";
import React from "react";
import {RouteDefinition, RouteParameters} from "./types";
import {getPath} from "./Router";

export type NavigateToOverlayFunction = (route: RouteDefinition, params?: RouteParameters, options?: NavigateOptions) => void;
export type CloseOverlayFunction = (options?: NavigateOptions) => void;

let lastHash: string | undefined;

export function useNavigateToOverlay(queryParam: string): NavigateToOverlayFunction {
    const navigate = useNavigate();

    return React.useCallback<NavigateToOverlayFunction>((route, params, options) => {
        if (!lastHash) {
            lastHash = document.location.hash;
        }
        const searchParams = new URLSearchParams(location.search);
        searchParams.set(queryParam, getPath(route, params));

        navigate({
            search: searchParams.toString(),
        }, options);
    }, []);
}

export function useCloseOverlay(queryParam: string): CloseOverlayFunction {
    const navigate = useNavigate();
    const location = useLocation();

    return React.useCallback<CloseOverlayFunction>((options) => {
        const searchParams = new URLSearchParams(location.search);
        searchParams.delete(queryParam);

        navigate({
            pathname: location.pathname,
            search: searchParams.toString(),
            hash: lastHash,
        }, options);

        lastHash = undefined;
    }, [navigate, location]);
}
