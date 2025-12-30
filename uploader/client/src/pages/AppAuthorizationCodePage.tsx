import {AuthorizationCodePage} from '@alchemy/react-auth';
import {oauthClient} from '../init';

type Props = {};

export default function AppAuthorizationCodePage({}: Props) {
    return <AuthorizationCodePage oauthClient={oauthClient} />;
}
