import {useLocation} from '@alchemy/navigation';
import {KeycloakClient} from "@alchemy/auth";

type Props = {
    keycloakClient: KeycloakClient;
    autoConnectIdP?: string | undefined;
}

export function useKeycloakUrls({
    autoConnectIdP,
    keycloakClient,
}: Props) {
    const location = useLocation();

    return {
        getLoginUrl: () =>
            keycloakClient.client.createAuthorizeUrl({
                connectTo: autoConnectIdP || undefined,
                state: btoa(JSON.stringify({r: location})),
            }),
        getAccountUrl: () => `${keycloakClient.getAccountUrl()}`,
    };
}
