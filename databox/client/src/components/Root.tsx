import React from 'react';
import {MatomoRouteProxy, ModalStack} from '@alchemy/navigation';
import UserPreferencesProvider from './User/Preferences/UserPreferencesProvider';
import {keycloakClient} from '../api/api-client';
import {AuthenticationProvider} from '@alchemy/auth';
import {RouterProvider} from '@alchemy/navigation';
import {routes} from '../routes.ts';

type Props = {};

export default function Root({}: Props) {
    const onLogout = React.useCallback((redirectUri: string | false = '/') => {
        keycloakClient.logout(redirectUri);
    }, []);

    return (
        <AuthenticationProvider onLogout={onLogout}>
            <ModalStack>
                <UserPreferencesProvider>
                    <RouterProvider
                        routes={routes}
                        RouteProxyComponent={MatomoRouteProxy}
                    />
                </UserPreferencesProvider>
            </ModalStack>
        </AuthenticationProvider>
    );
}
