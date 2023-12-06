import AuthorizationCodePage from '@alchemy/auth/src/components/AuthorizationCodePage.tsx';
import {oauthClient} from '../lib/apiClient';

type Props = {};

export default function AppAuthorizationCodePage({}: Props) {
    return <AuthorizationCodePage oauthClient={oauthClient} />;
}
