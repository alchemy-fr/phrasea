import AuthorizationCodePage from '@alchemy/auth/src/components/AuthorizationCodePage.tsx';
import {oauthClient} from '../api/api-client.ts';

type Props = {};

export default function AppAuthorizationCodePage({}: Props) {
    return <AuthorizationCodePage oauthClient={oauthClient} />;
}
