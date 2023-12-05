import config from '../config';
import {useLocation} from '@alchemy/navigation';
import {keycloakClient} from '../api/api-client';

export function useKeycloakUrls() {
    const location = useLocation();

    return {
        getLoginUrl: () =>
            keycloakClient.client.createAuthorizeUrl({
                connectTo: config.autoConnectIdP || undefined,
                state: btoa(JSON.stringify({r: location})),
            }),
        getAccountUrl: () => `${keycloakClient.getAccountUrl()}`,
    };
}
