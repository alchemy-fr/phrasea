import React from 'react';
import {ModalStack} from '@alchemy/navigation';
import UserPreferencesProvider from './User/Preferences/UserPreferencesProvider';
import {keycloakClient, oauthClient} from '../api/api-client';
import {AuthenticationProvider, MatomoUser} from '@alchemy/react-auth';
import {RouterProvider} from '@alchemy/navigation';
import {routes} from '../routes.ts';
import RouteProxy from "./Routing/RouteProxy.tsx";

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
            <ModalStack>
                <UserPreferencesProvider>
                    <RouterProvider
                        routes={routes}
                        options={{
                            RouteProxyComponent: RouteProxy,
                        }}
                    />
                </UserPreferencesProvider>
            </ModalStack>
        </AuthenticationProvider>
    );
}
