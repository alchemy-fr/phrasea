import {keycloakClient, oauthClient} from './lib/apiClient';
import {
    AuthenticationProvider,
    SessionExpireContainer,
    useAuthorizationCode,
} from '@alchemy/react-auth';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import Dashboard from './Dashboard';

type Props = {};

export default function Root({}: Props) {
    const {error, hasCode} = useAuthorizationCode({
        oauthClient,
        allowNoCode: true,
        navigate: (path, {replace} = {}) => {
            if (replace) {
                document.location.replace(path);
            } else {
                document.location.href = path;
            }
        },
        successUri: '/',
    });

    if (error) {
        return <div>{error.toString()}</div>;
    }

    return (
        <AuthenticationProvider
            oauthClient={oauthClient}
            keycloakClient={keycloakClient}
        >
            {hasCode && <FullPageLoader />}
            <SessionExpireContainer />
            <Dashboard />
        </AuthenticationProvider>
    );
}
