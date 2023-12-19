import {ModalStack} from '@alchemy/navigation';
import {AuthenticationProvider, MatomoUser} from '@alchemy/react-auth';
import UploaderUserProvider from './context/UploaderUserProvider';
import App from './App';
import {oauthClient} from './lib/apiClient';
import {ToastContainer} from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';

type Props = {};

export default function Root({}: Props) {
    return (
        <>
            <ToastContainer position={'bottom-left'} />
            <AuthenticationProvider
                oauthClient={oauthClient}
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
