import React from 'react';
import {createBrowserRouter, RouterProvider as RouterProviderBase} from "react-router-dom";
import {createRouterProviderRoutes} from "./Router";
import {Routes, RouteProxyProps, ErrorComponent} from "./types";
import DefaultRouteProxy from "./proxy/DefaultRouteProxy";

type Props = {
    routes: Routes;
    RouteProxyComponent?: React.FC<RouteProxyProps>;
    ErrorComponent?: ErrorComponent;
};

export default function RouterProvider({
    routes,
    RouteProxyComponent = DefaultRouteProxy,
    ErrorComponent = DefaultErrorComponent
}: Props) {
    const router = createBrowserRouter(createRouterProviderRoutes(routes, RouteProxyComponent, ErrorComponent));

    return <RouterProviderBase
        router={router}
    />
}

export function DefaultErrorComponent({
    error,
}: {
    error: any
}) {
    return <div>
        {error}
    </div>
}
