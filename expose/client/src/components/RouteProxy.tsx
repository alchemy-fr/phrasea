import type {RouteProxyProps} from '@alchemy/navigation';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {config, keycloakClient} from '../init.ts';

export default function RouteProxy({
    component: Component,
    public: isPublic = true,
}: RouteProxyProps) {
    const {isAuthenticated} = useAuth();
    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    if (!isPublic && !isAuthenticated) {
        document.location.href = getLoginUrl();

        return null;
    }

    return <Component />;
}
