import React from 'react';
import {ModalStack} from '@alchemy/navigation';
import {keycloakClient, oauthClient} from '../lib/api-client';
import {AuthenticationProvider, MatomoUser} from '@alchemy/react-auth';
import App from './App.tsx';

type Props = {};

export default function Root({}: Props) {
    const onLogout = React.useCallback((redirectUri: string | false = '/') => {
        keycloakClient.logout(redirectUri);
    }, []);

    return <AuthenticationProvider oauthClient={oauthClient} onLogout={onLogout}>
        <MatomoUser/>
        <ModalStack>
            <App/>
        </ModalStack>
    </AuthenticationProvider>
}
