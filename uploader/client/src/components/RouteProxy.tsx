import type {RouteProxyProps} from '@alchemy/navigation';
import {MatomoRouteProxy} from '@alchemy/navigation';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import config from '../config.ts';
import {keycloakClient} from '../lib/apiClient.ts';
import Menu from './Menu.tsx';

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

        return <></>;
    }

    return (
        <Menu>
            <MatomoRouteProxy component={Component} {...rest} />
        </Menu>
    );
}
