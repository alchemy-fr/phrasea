import {AuthorizationCodePage} from '@alchemy/react-auth';
import {oauthClient} from '../api/api-client.ts';

type Props = {};

export default function AppAuthorizationCodePage({}: Props) {
    return <AuthorizationCodePage oauthClient={oauthClient} />;
}
