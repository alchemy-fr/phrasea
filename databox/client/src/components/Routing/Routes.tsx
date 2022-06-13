import React from 'react';
import {Navigate, Route, Routes as BaseRoutes} from "react-router-dom";
import {appPathPrefix, RouteDefinition, routes} from "../../routes";
import createRoute from "./router";
import NotFound from "../../pages/NotFound";

export default function Routes() {
    return <>
        <BaseRoutes>
            {routes.map((route: RouteDefinition, index: number) => createRoute(route, index.toString()))}
            <Route index element={<Navigate to={appPathPrefix} replace/>}></Route>
            <Route path={'*'} element={<NotFound/>}></Route>
        </BaseRoutes>
    </>
}
