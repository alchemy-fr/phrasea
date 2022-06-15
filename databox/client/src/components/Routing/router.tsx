import React, {Component, FunctionComponent, useContext} from 'react';
import {Navigate, Route} from 'react-router-dom';
import {getPath, RouteDefinition} from "../../routes";
import {UserContext} from "../Security/UserContext";

type WrapperProps = {
    component: FunctionComponent<any>
} & RouteDefinition;

function RouteProxy({
                        component: Component,
                        public: isPublic,
                    }: WrapperProps) {
    const {user} = useContext(UserContext);

    if (!isPublic && !user) {
        return <Navigate to={getPath('login')}/>
    }

    return <Component/>
}

export default function createRoute(
    {
        component,
        routes,
        path,
        ...route
    }: RouteDefinition,
    key: string,
    pathPrefix: string = ''
) {
    const p = pathPrefix + path;

    if (routes && routes.length > 0) {
        return <Route
            key={key}
            path={p}
            element={<RouteProxy
                component={component!}
                path={p}
                {...route}
            />}
        >
            {routes.map(r => createRoute({
                ...r,
                path: r.path.substring(1),
                component: r.component || component,
            }, r.name))}
        </Route>
    }

    return <Route
        key={key}
        path={p}
        element={<RouteProxy
            component={component!}
            path={p}
            {...route}
        />}
    />
};
