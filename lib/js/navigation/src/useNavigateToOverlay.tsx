import { useNavigate} from "react-router-dom";
import React from "react";
import {RouteDefinition, RouteParameters} from "./types";
import {getPath} from "./Router";

export type NavigateToOverlayFunction = (route: RouteDefinition, params?: RouteParameters) => void;

export function useNavigateToOverlay(queryParam: string): NavigateToOverlayFunction {
    const navigate = useNavigate();

    return React.useCallback<NavigateToOverlayFunction>((route, params) => {
        navigate({
            search: `${queryParam}=${getPath(route, params)}`,
        });
    }, [navigate]);
}
