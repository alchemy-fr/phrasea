import {ModalStack} from '@alchemy/navigation';
import {oauthClient} from '../lib/api-client';
import {AuthenticationProvider, MatomoUser, SessionExpireContainer} from '@alchemy/react-auth';
import App from './App.tsx';
import {ToastContainer} from 'react-toastify';

type Props = {};

export default function Root({}: Props) {
    return (
        <>
            <ToastContainer position={'bottom-left'} />
            <AuthenticationProvider oauthClient={oauthClient}>
                <SessionExpireContainer/>
                <MatomoUser />
                <ModalStack>
                    <App />
                </ModalStack>
            </AuthenticationProvider>
        </>
    );
}
