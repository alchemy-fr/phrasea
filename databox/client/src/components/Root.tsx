import {
    MatomoRouteWrapper,
    OverlayOutlet,
    RouterProvider,
    RouteWrapperProps,
} from '@alchemy/navigation';
import UserPreferencesProvider from './User/Preferences/UserPreferencesProvider';
import {SessionExpireContainer} from '@alchemy/react-auth';
import {modalRoutes, routes} from '../routes';
import RouteProxy from './Routing/RouteProxy';
import AttributeFormatProvider from './Media/Asset/Attribute/Format/AttributeFormatProvider.tsx';
import React from 'react';
import {QueryClientProvider} from '@tanstack/react-query';
import {queryClient} from '../lib/query.ts';

type Props = {};

export default function Root({}: Props) {
    return (
        <>
            <QueryClientProvider client={queryClient}>
                <AttributeFormatProvider>
                    <UserPreferencesProvider>
                        <RouterProvider
                            routes={routes}
                            options={{
                                RouteProxyComponent: RouteProxy,
                                WrapperComponent: WrapperComponent,
                            }}
                        />
                    </UserPreferencesProvider>
                </AttributeFormatProvider>
            </QueryClientProvider>
        </>
    );
}

function WrapperComponent({children}: RouteWrapperProps) {
    return (
        <>
            <SessionExpireContainer />
            <MatomoRouteWrapper>
                <OverlayOutlet
                    routes={modalRoutes}
                    queryParam={'_m'}
                    RouteProxyComponent={RouteProxy}
                />
                {children}
            </MatomoRouteWrapper>
        </>
    );
}
