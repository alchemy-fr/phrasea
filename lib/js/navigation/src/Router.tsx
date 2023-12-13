import {
    RouteDefinition,
    RouteParameters,
    Routes,
    RouteProxyProps,
    RouteProxyComponent,
    TErrorBoundaryComponent, TErrorFallbackComponent,
} from "./types";
import {getFullPath, getLocationPrefix} from "./utils";
import {Outlet, RouteObject} from "react-router-dom";
import React, {PropsWithChildren} from "react";
import DefaultRouteProxy from "./proxy/DefaultRouteProxy";
import {NotFoundPage, ErrorPage} from "@alchemy/phrasea-ui";
import {ErrorBoundary} from "@alchemy/core";


export function compileRoutes(routes: Routes, rootUrl?: string): Routes {
    rootUrl ??= getLocationPrefix();

    const toRouteObject = (
        route: RouteDefinition,
        parentRoute?: RouteDefinition,
    ): RouteDefinition => {
        const compiled: RouteDefinition = {
            ...route,
            public: route.public || parentRoute?.public,
            layout: route.layout || parentRoute?.layout,
            rootUrl: route.rootUrl ?? parentRoute?.rootUrl ?? rootUrl,
            parent: parentRoute,
        };

        const compiledSubRoutes: Routes = {};
        const subRoutes = route.routes;
        if (subRoutes) {
            Object.keys(subRoutes).forEach(r => {
                compiledSubRoutes[r] = toRouteObject(subRoutes[r], compiled);
            });
            compiled.routes = compiledSubRoutes;
        }

        return compiled;
    };

    const compiled: Routes = {};
    Object.keys(routes).forEach(r => {
        compiled[r] = toRouteObject(routes[r]);
    });

    return compiled;
}

// Get path (ex: getPath(route.user.edit, {id: '1'}))
export function getPath(
    route: RouteDefinition,
    params?: RouteParameters,
    options: {
        noRedirectPath?: boolean;
        absoluteUrl?: boolean;
    } = {},
): string {
    if (!options.noRedirectPath) {
        if (!route.component && !route.rootUrl) {
            if (!route.routes || Object.keys(route.routes).length === 0) {
                throw Error('OO8A81'); // Dev error: No component is defined neither no sub routes, so we can't redirect to the first child
            }

            return getPath(route.routes[Object.keys(route.routes)[0]!]!);
        }
    }

    let path = resolvePath(getFullPath(route), params);

    if (options.absoluteUrl) {
        path = (route.rootUrl ?? getLocationPrefix()) + path;
    }

    return path || '/';
}

function resolvePath(uriTemplate: string, params?: RouteParameters): string {
    let path = uriTemplate;
    const qs: Record<string, string> = {};

    if (params) {
        Object.entries(params).forEach(([key, value]) => {
            if (path.indexOf(`:${key}`) >= 0) {
                path = path ? path.replace(`:${key}`, value || '') : '';
            } else {
                qs[key] = value || '';
            }
        });
    }

    const queryStringKeys = Object.keys(qs);
    if (queryStringKeys.length > 0) {
        const u = new URL(path);
        queryStringKeys.map((key) => {
            u.searchParams.set(key, qs[key]);
        });

        path = u.toString();
    }

    return path;
}

export function createRouteComponent(route: RouteDefinition, RouteProxyComponent: React.FC<RouteProxyProps>) {
    if (!route.component) {
        return;
    }

    return () => <RouteProxyComponent
        {...(route as RouteProxyProps)}
        path={getFullPath(route)}
    />
}

export type RouterProviderOptions = {
    RouteProxyComponent?: RouteProxyComponent,
    ErrorComponent?: TErrorFallbackComponent,
    ErrorBoundaryComponent?: TErrorBoundaryComponent,
    WrapperComponent?: React.FC<PropsWithChildren<{}>>;
}

export function createRouterProviderRoutes(
    routes: Routes,
    options: RouterProviderOptions,
): RouteObject[] {
    const output: RouteObject[] = [];

    const {
        RouteProxyComponent: RouteProxyComponent = DefaultRouteProxy,
        ErrorComponent = ErrorPage,
        ErrorBoundaryComponent = ErrorBoundary,
        WrapperComponent
    } = options;

    const toRouter = (r: RouteDefinition): RouteObject => {
        return {
            path: r.path,
            Component: createRouteComponent(r, RouteProxyComponent),
            children: r.routes
                ? Object.keys(r.routes).map(k => {
                    return toRouter(r.routes![k]);
                })
                : undefined,
            loader: r.loader,
            action: r.action,
        } as RouteObject;
    };

    Object.keys(routes).forEach(r => {
        output.push(toRouter(routes[r]));
    });

    output.push({
        id: 'not_found',
        path: '*',
        Component: () => <NotFoundPage/>,
    });

    return [
        {
            Component: () => <ErrorBoundaryComponent fallback={ErrorComponent}>
                {WrapperComponent ? React.createElement(WrapperComponent, {
                    children: <Outlet />
                }) : <Outlet />}
            </ErrorBoundaryComponent>,
            children: output,
        },
    ] as RouteObject[];
}
