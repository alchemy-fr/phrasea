import {MatomoRouteWrapper, RouterProvider} from '@alchemy/navigation';
import {routes} from '../routes.ts';
import RouteProxy from './RouteProxy.tsx';
import React from 'react';

type Props = {};

export default function App({}: Props) {
    return (
        <>
            <RouterProvider
                routes={routes}
                options={{
                    RouteProxyComponent: RouteProxy,
                    WrapperComponent: MatomoRouteWrapper,
                }}
            />
        </>
    );
}
