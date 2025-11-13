import {ModalStack} from '@alchemy/navigation';
import {keycloakClient, oauthClient} from '../lib/api-client';
import {AuthenticationProvider, MatomoUser} from '@alchemy/react-auth';
import App from './App.tsx';
import {ToastContainer} from 'react-toastify';

type Props = {};

export default function Root({}: Props) {
    return (
        <>
            <ToastContainer position={'bottom-left'} />
            <AuthenticationProvider
                oauthClient={oauthClient}
                keycloakClient={keycloakClient}
            >
                <MatomoUser />
                <ModalStack>
                    <App />
                </ModalStack>
            </AuthenticationProvider>
        </>
    );
}
