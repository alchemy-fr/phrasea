import { useNavigate} from "react-router-dom";
import React from "react";
import {getPath, RouteDefinition, RouteParameters} from "../routes";

export type NavigateToDrawerFunction = (route: RouteDefinition, params?: RouteParameters) => void;

export const DRAWER_PARAM = '_d';

export function useNavigateToDrawer(): NavigateToDrawerFunction {
    const navigate = useNavigate();

    return React.useCallback<NavigateToDrawerFunction>((route, params) => {
        navigate({
            search: `${DRAWER_PARAM}=${getPath(route, params)}`,
        });
    }, [navigate]);
}
