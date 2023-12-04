import React from 'react';
import {matchRoutes, renderMatches, RouteObject} from "react-router-dom";
import {getOverlayRouteContext, TOverlayRouteContext} from "./OverlayRouteContext";
import {RouteProxyComponent, Routes} from "../types";
import {createRouterProviderRoutes} from "../Router";

type Props = {
    queryParam: string;
    path: string;
    routes: Routes;
    RouteProxyComponent: RouteProxyComponent,
};

export default React.memo(function OverlayRouterProvider({
    queryParam,
    routes: routeDefinitions,
    path,
    RouteProxyComponent,
}: Props) {
    const routes: RouteObject[] = React.useMemo(() => createRouterProviderRoutes(routeDefinitions), []);

    const matches = matchRoutes(routes, {
        pathname: path,
    });

    const contextValue = React.useMemo<TOverlayRouteContext>(() => {
        return {
            path,
            params: matches ? matches[matches!.length - 1].params : {},
        }
    }, [path]);

    const OverlayRouteContext = getOverlayRouteContext(queryParam);

    return <OverlayRouteContext.Provider value={contextValue}>
        {renderMatches(matches)}
    </OverlayRouteContext.Provider>
});
