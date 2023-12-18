import {MatomoRouteWrapper} from '@alchemy/navigation';
import type {RouteProxyProps} from '@alchemy/navigation';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {keycloakClient} from '../lib/api-client';
import config from '../config';

export default function RouteProxy({
    component: Component,
    public: isPublic = true,
    ...rest
}: RouteProxyProps) {
    const {isAuthenticated} = useAuth();
    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    if (!isPublic && !isAuthenticated()) {
        document.location.href = getLoginUrl();

        return <></>;
    }

    return <MatomoRouteWrapper component={Component} {...rest} />;
}
