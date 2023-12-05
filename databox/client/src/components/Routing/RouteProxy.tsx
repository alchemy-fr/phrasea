import {useKeycloakUrls} from '../../lib/keycloak';
import {MatomoRouteProxy} from '@alchemy/navigation';
import type {RouteProxyProps} from '@alchemy/navigation';
import {useUser} from "../../hooks/useUser.ts";

export default function RouteProxy({
    component: Component,
    public: isPublic,
    ...rest
}: RouteProxyProps) {
    const {user} = useUser();
    const {getLoginUrl} = useKeycloakUrls();

    if (!isPublic && !user) {
        document.location.href = getLoginUrl();

        return <></>
    }

    return <MatomoRouteProxy
        component={Component}
        {...rest}
    />
}



