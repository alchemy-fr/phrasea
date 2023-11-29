import React, {ElementType, FunctionComponent, PropsWithChildren} from "react";
import {ActionFunction, LoaderFunction} from "react-router-dom";

export type RouteDefinition = {
    path: string;
    rootUrl?: string;
    component?: FunctionComponent<any>;
    layout?: FunctionComponent<PropsWithChildren<any>>;
    routes?: Routes;
    public?: boolean;
    parent?: RouteDefinition;
    action?: ActionFunction;
    loader?: LoaderFunction;
};

export type RouteProxyProps = {
    component: React.ComponentType;
} & RouteDefinition;


export type Routes = {
    [routeName: string]: RouteDefinition;
};

export type RouteParameters = Record<string, string | undefined | null>;

export type RouteProxyComponent = FunctionComponent<RouteProxyProps>;
export type ErrorComponent = ElementType;
