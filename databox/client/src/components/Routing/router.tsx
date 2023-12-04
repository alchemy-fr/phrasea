import {useContext} from 'react';
import {UserContext} from '../Security/UserContext';
import {useKeycloakUrls} from '../../lib/keycloak';
import type {RouteProxyProps} from '@alchemy/navigation';

export function RouteProxy({
    component: Component,
    public: isPublic,
}: RouteProxyProps) {
    const {user} = useContext(UserContext);
    const {getLoginUrl} = useKeycloakUrls();

    if (!isPublic && !user) {
        document.location.href = getLoginUrl();

        return <></>;
    }

    return <Component />;
}
