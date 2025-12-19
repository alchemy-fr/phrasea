import type {RouteProxyProps} from '@alchemy/navigation';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import config from '../config.ts';
import {keycloakClient} from '../lib/apiClient.ts';

export default function RouteProxy({
    component: Component,
    public: isPublic,
}: RouteProxyProps) {
    const {isAuthenticated} = useAuth();
    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    if (!isPublic && !isAuthenticated) {
        document.location.href = getLoginUrl();

        return <></>;
    }

    return <Component />;
}
