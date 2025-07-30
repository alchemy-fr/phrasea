import React, {ElementType, FunctionComponent, PropsWithChildren} from 'react';
import type {ActionFunction, LoaderFunction} from 'react-router-dom';
import {NavigateOptions} from "react-router-dom";

export type RouteDefinition = {
    path: string;
    rootUrl?: string;
    component?: FunctionComponent<any> | React.Component<any>;
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

export type RouteWrapperProps = PropsWithChildren<{}>;

export type ErrorFallbackProps = {error: any};
export type TErrorFallbackComponent = (
    props: ErrorFallbackProps
) => React.JSX.Element;

export type TErrorBoundaryComponent = React.JSXElementConstructor<
    PropsWithChildren<{
        fallback: TErrorFallbackComponent;
    }>
>;

export type {Location, Path, To} from 'react-router-dom';

export type NavigateToOverlayProps = {
    route: RouteDefinition;
    params?: RouteParameters;
    options?: NavigateOptions;
    hash?: string;
}
