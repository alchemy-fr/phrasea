import React, {Component, FunctionComponent, useContext} from 'react';
import {Navigate, Route} from 'react-router-dom';
import {getPath, RouteDefinition} from "../../routes";
import {UserContext} from "../Security/UserContext";

type WrapperProps = {
    component: FunctionComponent<any>
} & RouteDefinition;

function RouteProxy({
                        component: Component,
                        private: isPrivate,
                    }: WrapperProps) {
    const {user} = useContext(UserContext);

    if (isPrivate && !user) {
        return <Navigate to={getPath('login')}/>
    }

    return <Component/>
}

export default function createRoute(
    {
        component,
        ...route
    }: RouteDefinition,
    key: string
) {
    return <Route
        key={key}
        path={route.path}
        element={<RouteProxy
            component={component!}
            {...route}
        />}
    />
};
