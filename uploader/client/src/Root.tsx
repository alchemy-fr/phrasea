import React from 'react';
import {ModalStack} from '@alchemy/navigation';
import {AuthenticationProvider} from '@alchemy/auth';
import UploaderUserProvider from "./context/UploaderUserProvider";
import App from "./App";
import {keycloakClient, oauthClient} from "./lib/apiClient";

type Props = {};

export default function Root({}: Props) {
    const onLogout = React.useCallback((redirectUri: string | false = '/') => {
        keycloakClient.logout(redirectUri);
    }, []);

    return (
        <AuthenticationProvider oauthClient={oauthClient} onLogout={onLogout}>
            <UploaderUserProvider>
                <ModalStack>
                    <App/>
                </ModalStack>
            </UploaderUserProvider>
        </AuthenticationProvider>
    );
}
