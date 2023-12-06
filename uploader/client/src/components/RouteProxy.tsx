import {MatomoRouteProxy} from '@alchemy/navigation';
import type {RouteProxyProps} from '@alchemy/navigation';
import {useAuth, useKeycloakUrls} from '@alchemy/auth';
import config from "../lib/config.ts";
import {keycloakClient} from "../lib/apiClient.ts";

export default function RouteProxy({
    component: Component,
    public: isPublic,
    ...rest
}: RouteProxyProps) {
    const {isAuthenticated} = useAuth();
    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    if (!isPublic && !isAuthenticated()) {
        document.location.href = getLoginUrl();

        return <></>
    }

    return <MatomoRouteProxy
        component={Component}
        {...rest}
    />
}



