import {AuthorizationCodePage} from '@alchemy/react-auth';
import {oauthClient} from '../api/api-client';

type Props = {};

export default function AppAuthorizationCodePage({}: Props) {
    return <AuthorizationCodePage oauthClient={oauthClient} />;
}
