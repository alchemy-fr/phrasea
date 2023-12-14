import {MatomoRouteProxy, OverlayOutlet} from '@alchemy/navigation';
import type {RouteProxyProps} from '@alchemy/navigation';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {keycloakClient} from '../../api/api-client.ts';
import config from '../../config.ts';
import {modalRoutes} from "../../routes.ts";

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

    return <MatomoRouteProxy
        component={() => <>
            <OverlayOutlet
                routes={modalRoutes}
                queryParam={'_m'}
            />
            <Component/>
        </>}
        {...rest}
    />;
}
