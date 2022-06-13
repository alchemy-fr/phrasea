import {FunctionComponent} from "react";
import App from "./components/App";
import Login from "./pages/Security/Login";
import OAuthRedirect from "./oauth";
import WorkspaceDialog from "./components/Dialog/Workspace/WorkspaceDialog";

export type RouteDefinition = {
    name: string;
    path: string;
    component?: FunctionComponent;
    layout?: FunctionComponent;
    routes?: RouteDefinition[];
    public?: boolean;
}

export const appPathPrefix = '/app';

export const modalRoutes = [
    {
        name: 'workspace_manage',
        path: '/workspaces/:id/manage/:tab',
        component: WorkspaceDialog,
        public: false,
    },
];

export const routes: RouteDefinition[] = [
    {
        name: 'app',
        path: appPathPrefix,
        component: App,
        routes: modalRoutes,
        public: true,
    },
    {
        name: 'login',
        path: '/login',
        component: Login,
        public: true,
    },
    {
        name: 'oauth',
        path: '/auth',
        component: OAuthRedirect,
        public: true,
    },
];

function compile(parentRoute: RouteDefinition, subRoutes: RouteDefinition[]): RouteDefinition[] {
    return subRoutes.flatMap<RouteDefinition>((subRoute) => {
        const newRoute: RouteDefinition = {
            name: (parentRoute.name ? (parentRoute.name + '_') : '') + subRoute.name,
            path: parentRoute.path.replace(/\/$/, '') + subRoute.path,
            component: subRoute.component || parentRoute.component,
            public: subRoute.public ?? parentRoute.public,
            layout: subRoute.layout || parentRoute.layout,
        };

        return subRoute.routes ? [newRoute, ...compile(newRoute, subRoute.routes)] : newRoute;
    });
}

export const flattenRoutes = getFlattenRoutes(routes);

function getFlattenRoutes(routes: RouteDefinition[], pathPrefix: string = ''): RouteDefinition[] {
    const parentRoute: RouteDefinition = {
        name: '',
        path: pathPrefix,
    };

    return compile(parentRoute, routes);
}

function getRoutePath(flattenRoutes: RouteDefinition[], name: string, params?: RouteParams): string {
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

export type RouteParams = Record<string, any>;

// Get path (ex: getPath('user', {id: '1'}))
export function getPath(name: string, params?: RouteParams): string {
    return getRoutePath(flattenRoutes, name, params);
}
