import {
    createBrowserRouter,
    RouterProvider as RouterProviderBase,
} from 'react-router-dom';
import {createRouterProviderRoutes, RouterProviderOptions} from './Router';
import {Routes} from './types';
import React from 'react';

type Props = {
    routes: Routes;
    options: RouterProviderOptions;
};

export default function RouterProvider({routes, options = {}}: Props) {
    const router = React.useMemo(
        () => createBrowserRouter(createRouterProviderRoutes(routes, options)),
        []
    );

    return <RouterProviderBase router={router} />;
}
