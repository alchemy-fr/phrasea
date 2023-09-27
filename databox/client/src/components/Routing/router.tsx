import React, {Component, FunctionComponent, useContext} from 'react';
import {Route} from 'react-router-dom';
import {RouteDefinition} from "../../routes";
import {UserContext} from "../Security/UserContext";
import {useKeycloakUrls} from "../../lib/keycloak";

type WrapperProps = {
    component: FunctionComponent<any>
} & RouteDefinition;

function RouteProxy({
    component: Component,
    public: isPublic,
}: WrapperProps) {
    const {user} = useContext(UserContext);
    const {getLoginUrl} = useKeycloakUrls();

    if (!isPublic && !user) {
        document.location.href = getLoginUrl();

        return <></>
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
