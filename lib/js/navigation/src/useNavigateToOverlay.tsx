import {useLocation, useNavigate} from "react-router-dom";
import React from "react";
import {RouteDefinition, RouteParameters} from "./types";
import {getPath} from "./Router";

export type NavigateToOverlayFunction = (route: RouteDefinition, params?: RouteParameters) => void;
export type CloseOverlayFunction = () => void;

export function useNavigateToOverlay(queryParam: string): NavigateToOverlayFunction {
    // const navigate = useNavigate();

    return React.useCallback<NavigateToOverlayFunction>((route, params) => {
        history.pushState({}, "", `${queryParam}=${getPath(route, params)}`);

        // navigate({
        //     search: `${queryParam}=${getPath(route, params)}`,
        // });
    }, []);
}

export function useCloseOverlay(queryParam: string): CloseOverlayFunction {
    const navigate = useNavigate();
    const location = useLocation();

    return React.useCallback(() => {
        const searchParams = new URLSearchParams(location.search);
        searchParams.delete(queryParam);

        navigate({
            pathname: location.pathname,
            search: searchParams.toString(),
        });
    }, [navigate, location]);
}
