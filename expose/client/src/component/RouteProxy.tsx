import {MatomoRouteProxy} from '@alchemy/navigation';
import type {RouteProxyProps} from '@alchemy/navigation';
import {useAuth, useKeycloakUrls} from '@alchemy/auth';
import {keycloakClient} from "../lib/api-client.ts";
import config from "../lib/config.ts";

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



