import {oauthClient} from './lib/apiClient';
import {AuthenticationProvider, SessionExpireContainer, useAuthorizationCode} from '@alchemy/react-auth';
import Dashboard from "./Dashboard.tsx";

type Props = {};

export default function Root({}: Props) {
    useAuthorizationCode({
        oauthClient,
        navigate: (path, {replace} = {}) => {
            if (replace) {
                document.location.replace(path);
            } else {
                document.location.href = path
            }
        },
        successUri: '/'
    });

    return (
        <AuthenticationProvider oauthClient={oauthClient}>
            <SessionExpireContainer/>
            <Dashboard />
        </AuthenticationProvider>
    );
}
