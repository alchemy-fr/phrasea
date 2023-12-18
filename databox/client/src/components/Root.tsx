import React from 'react';
import {MatomoRouteWrapper, ModalStack, OverlayOutlet, RouterProvider, RouteWrapperProps} from '@alchemy/navigation';
import UserPreferencesProvider from './User/Preferences/UserPreferencesProvider';
import {keycloakClient, oauthClient} from '../api/api-client';
import {AuthenticationProvider, MatomoUser} from '@alchemy/react-auth';
import {modalRoutes, routes} from '../routes.ts';
import RouteProxy from './Routing/RouteProxy.tsx';

type Props = {};

export default function Root({}: Props) {
    const onLogout = React.useCallback((redirectUri: string | false = '/') => {
        keycloakClient.logout(redirectUri);
    }, []);

    return (
        <AuthenticationProvider
            oauthClient={oauthClient}
            onLogout={onLogout}
        >
            <MatomoUser/>
            <UserPreferencesProvider>
                <ModalStack>
                    <RouterProvider
                        routes={routes}
                        options={{
                            RouteProxyComponent: RouteProxy,
                            WrapperComponent: WrapperComponent,
                        }}
                    />
                </ModalStack>
            </UserPreferencesProvider>
        </AuthenticationProvider>
    );
}

function WrapperComponent({children}: RouteWrapperProps) {
    return <>
        <OverlayOutlet
            routes={modalRoutes}
            queryParam={'_m'}
        />
        <MatomoRouteWrapper>
            {children}
        </MatomoRouteWrapper>
    </>
}
