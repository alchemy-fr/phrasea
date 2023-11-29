import React from 'react';
import {matchRoutes, renderMatches, RouteObject} from "react-router-dom";
import DrawerRouteContext, {TDrawerRouteContext} from "./DrawerRouteContext";
import {Routes} from "../types";
import {createRouterProviderRoutes} from "../Router";

type Props = {
    path: string;
    routes: Routes;
};

export default React.memo(function DrawerRouterProvider({
    routes,
    path,
}: Props) {
    const routes: RouteObject[] = React.useMemo(() => createRouterProviderRoutes(routes), []);

    const matches = matchRoutes(routes, {
        pathname: path,
    });

    const contextValue = React.useMemo<TDrawerRouteContext>(() => {
        return {
            path,
            params: matches ? matches[matches!.length - 1].params : {},
        }
    }, [path]);

    return <DrawerRouteContext.Provider value={contextValue}>
        {renderMatches(matches)}
    </DrawerRouteContext.Provider>
});
