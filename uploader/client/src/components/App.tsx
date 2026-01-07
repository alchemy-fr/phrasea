import {
    MatomoRouteWrapper,
    RouterProvider,
    RouteWrapperProps,
} from '@alchemy/navigation';
import {routes} from '../routes.ts';
import RouteProxy from './RouteProxy.tsx';
import React from 'react';
import LeftMenu from './LeftMenu.tsx';
import {VerticalMenuLayout} from '@alchemy/phrasea-framework';

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
                <VerticalMenuLayout>
                    <LeftMenu />
                    <div
                        style={{
                            flexGrow: 1,
                        }}
                    >
                        {children}
                    </div>
                </VerticalMenuLayout>
            </MatomoRouteWrapper>
        </>
    );
}
