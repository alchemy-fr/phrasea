import type {RouteProxyProps} from '@alchemy/navigation';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import config from '../config.ts';
import {keycloakClient} from "../lib/api-client.ts";

export default function RouteProxy({
    component: Component,
    public: isPublic = true,
}: RouteProxyProps) {
    const {isAuthenticated} = useAuth();
    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    if (!isPublic && !isAuthenticated()) {
        document.location.href = getLoginUrl();

        return null
    }

    return <Component/>
}
