import {getCurrentPath} from '@alchemy/navigation';
import {KeycloakClient} from '@alchemy/auth';
import {useEffect} from "react";

type Props = {
    keycloakClient: KeycloakClient;
    autoConnectIdP?: string | undefined;
    silentConnect?: boolean;
};

export type {Props as UseKeycloakUrlProps};

export function useKeycloakUrls({autoConnectIdP, keycloakClient, silentConnect = true}: Props) {
    const getLoginUrl = (redirectUri?: string) =>
        keycloakClient.client.createAuthorizeUrl({
            connectTo: autoConnectIdP || undefined,
            state: btoa(
                JSON.stringify({r: redirectUri ?? getCurrentPath()})
            ),
        });

    useEffect(() => {
        if (silentConnect) {
            (async () => {
                if (!keycloakClient.client.isAuthenticated() && await keycloakClient.hasKeycloakSession()) {
                    document.location.href = getLoginUrl();
                }
            })();
        }
    }, []);

    return {
        getLoginUrl,
        getAccountUrl: () => `${keycloakClient.getAccountUrl()}`,
    };
}
