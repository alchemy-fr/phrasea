import {
    MatomoRouteWrapper,
    ModalStack,
    OverlayOutlet,
    RouterProvider,
    RouteWrapperProps,
} from '@alchemy/navigation';
import {
    AuthenticationProvider,
    SessionExpireContainer,
} from '@alchemy/react-auth';
import {modalRoutes, routes} from '../routes';
import RouteProxy from './Routing/RouteProxy';
import AttributeFormatProvider from './Media/Asset/Attribute/Format/AttributeFormatProvider.tsx';
import React from 'react';
import {QueryClientProvider} from '@tanstack/react-query';
import {queryClient} from '../lib/query.ts';
import {ToastContainer} from 'react-toastify';
import {
    AnalyticsProvider,
    AppGlobalTheme,
    UserHookCaller,
} from '@alchemy/phrasea-framework';
import {config, keycloakClient, matomo, oauthClient} from '../init.ts';
import UserPreferencesProvider from './User/Preferences/UserPreferencesProvider.tsx';

type Props = {};

export default function Root({}: Props) {
    const css = config.globalCSS;

    return (
        <>
            <UserPreferencesProvider>
                {css && <style>{css}</style>}
                <AppGlobalTheme>
                    <AnalyticsProvider matomo={matomo}>
                        <ToastContainer position={'bottom-left'} />
                        <AuthenticationProvider
                            oauthClient={oauthClient}
                            keycloakClient={keycloakClient}
                        >
                            <UserHookCaller />

                            <QueryClientProvider client={queryClient}>
                                <AttributeFormatProvider>
                                    <RouterProvider
                                        routes={routes}
                                        options={{
                                            RouteProxyComponent: RouteProxy,
                                            WrapperComponent: WrapperComponent,
                                        }}
                                    />
                                </AttributeFormatProvider>
                            </QueryClientProvider>
                        </AuthenticationProvider>
                    </AnalyticsProvider>
                </AppGlobalTheme>
            </UserPreferencesProvider>
        </>
    );
}

function WrapperComponent({children}: RouteWrapperProps) {
    return (
        <>
            <ModalStack>
                <SessionExpireContainer />
                <MatomoRouteWrapper>
                    <OverlayOutlet
                        routes={modalRoutes}
                        queryParam={'_m'}
                        RouteProxyComponent={RouteProxy}
                    />
                    {children}
                </MatomoRouteWrapper>
            </ModalStack>
        </>
    );
}
