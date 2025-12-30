import {
    MatomoRouteWrapper,
    RouterProvider,
    RouteWrapperProps,
} from '@alchemy/navigation';
import {routes} from '../routes.ts';
import RouteProxy from './RouteProxy.tsx';
import React from 'react';
import LeftMenu from './LeftMenu.tsx';

type Props = {};

export default function App({}: Props) {
    return (
        <>
            <RouterProvider
                routes={routes}
                options={{
                    RouteProxyComponent: RouteProxy,
                    WrapperComponent: WrapperComponent,
                }}
            />
        </>
    );
}

function WrapperComponent({children}: RouteWrapperProps) {
    return (
        <>
            <MatomoRouteWrapper>
                <div
                    style={{
                        height: '100vh',
                        display: 'flex',
                    }}
                >
                    <LeftMenu />
                    <div
                        style={{
                            flexGrow: 1,
                        }}
                    >
                        {children}
                    </div>
                </div>
            </MatomoRouteWrapper>
        </>
    );
}
