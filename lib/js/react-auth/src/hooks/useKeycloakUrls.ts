import {getCurrentPath} from '@alchemy/navigation';
import {KeycloakClient} from "@alchemy/auth";

type Props = {
    keycloakClient: KeycloakClient;
    autoConnectIdP?: string | undefined;
}

export type {Props as UseKeycloakUrlProps};

export function useKeycloakUrls({
    autoConnectIdP,
    keycloakClient,
}: Props) {
    return {
        getLoginUrl: () =>
            keycloakClient.client.createAuthorizeUrl({
                connectTo: autoConnectIdP || undefined,
                state: btoa(JSON.stringify({r: getCurrentPath()})),
            }),
        getAccountUrl: () => `${keycloakClient.getAccountUrl()}`,
    };
}
