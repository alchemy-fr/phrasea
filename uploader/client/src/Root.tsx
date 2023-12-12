import React from 'react';
import {ModalStack} from '@alchemy/navigation';
import {AuthenticationProvider, MatomoUser} from '@alchemy/react-auth';
import UploaderUserProvider from './context/UploaderUserProvider';
import App from './App';
import {keycloakClient, oauthClient} from './lib/apiClient';
import FullPageLoader from './components/FullPageLoader.jsx';
import {ToastContainer} from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';

type Props = {};

export default function Root({}: Props) {
    const [redirecting, setRedirecting] = React.useState(false);
    const onLogout = React.useCallback((redirectUri: string | false = '/') => {
        setRedirecting(true);
        keycloakClient.logout(redirectUri);
    }, []);

    if (redirecting) {
        return <FullPageLoader />;
    }

    return (
        <>
            <ToastContainer position={'bottom-left'} />
            <AuthenticationProvider
                oauthClient={oauthClient}
                onLogout={onLogout}
            >
                <MatomoUser />
                <UploaderUserProvider>
                    <ModalStack>
                        <App />
                    </ModalStack>
                </UploaderUserProvider>
            </AuthenticationProvider>
        </>
    );
}
