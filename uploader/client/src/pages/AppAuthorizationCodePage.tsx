import {AuthorizationCodePage} from '@alchemy/react-auth';
import {oauthClient} from '../lib/apiClient';

type Props = {};

export default function AppAuthorizationCodePage({}: Props) {
    return <AuthorizationCodePage oauthClient={oauthClient} />;
}
