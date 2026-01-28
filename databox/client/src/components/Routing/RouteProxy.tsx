import type {RouteProxyProps} from '@alchemy/navigation';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import config from '../../config';
import {keycloakClient} from '../../init.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';

export default function RouteProxy({
    component: Component,
    public: isPublic = false,
}: RouteProxyProps) {
    const {isAuthenticated, hasSession} = useAuth();
    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    if (!isPublic && !isAuthenticated) {
        if (!hasSession) {
            // No session, redirect to login
            document.location.href = getLoginUrl();
        }

        return <FullPageLoader backdrop={false} />;
    }

    return <Component />;
}
