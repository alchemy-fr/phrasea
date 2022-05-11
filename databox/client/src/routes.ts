import {FunctionComponent} from "react";
import App from "./components/App";
import Login from "./pages/Security/Login";
import OAuthRedirect from "./oauth";

export type RouteDefinition = {
    name: string;
    path: string;
    component?: FunctionComponent;
    layout?: FunctionComponent;
    routes?: RouteDefinition[];
    private?: boolean;
}

const routes: RouteDefinition[] = [
    {
        name: 'app',
        path: '/',
        component: App,
    },
    {
        name: 'login',
        path: '/login',
        component: Login,
    },
    {
        name: 'oauth',
        path: '/auth',
        component: OAuthRedirect,
    },
];

function compile(parentRoute: RouteDefinition, subRoutes: RouteDefinition[]): RouteDefinition[] {
    return subRoutes.flatMap<RouteDefinition>((subRoute) => {
        const newRoute: RouteDefinition = {
            name: subRoute.name,
            path: parentRoute.path.replace(/\/$/, '') + subRoute.path,
            component: subRoute.component,
            private: subRoute.private || parentRoute.private,
            layout: subRoute.layout || parentRoute.layout,
        };

        return subRoute.routes ? [newRoute, ...compile(newRoute, subRoute.routes)] : newRoute;
    });
}

export const flattenRoutes = getRoutes();

function getRoutes(): RouteDefinition[] {
    const parentRoute: RouteDefinition = {
        name: '',
        path: '',
    };

    return compile(parentRoute, routes);
}

// Get path (ex: getPath('user', {id: '1'}))
export function getPath(name: string, params?: Record<string, any>): string {
    const routeFound = flattenRoutes.find(route => route.name === name);
    if (!routeFound) {
        throw new Error(`Route "${name}" not found`);
    }

    let path = routeFound.path;
    if (params) {
        Object.entries(params).forEach(([key, value]) => {
            path = path ? path.replace(`:${key}`, value) : '';
        });
    }

    return path;
}
