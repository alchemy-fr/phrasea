import {getCurrentUrl} from '@alchemy/navigation';
import {AuthConstant, KeycloakClient} from '@alchemy/auth';

type Props = {
    keycloakClient: KeycloakClient;
    autoConnectIdP?: string | undefined;
};

export type {Props as UseKeycloakUrlProps};

export function useKeycloakUrls({autoConnectIdP, keycloakClient}: Props) {
    const getLoginUrl = (redirectUri?: string) => {
        let redirectPath = redirectUri;
        if (!redirectPath) {
            const currentUrl = getCurrentUrl();
            currentUrl.searchParams.delete(AuthConstant.LoggedOutParam);
            redirectPath = currentUrl.toString();
        }

        return keycloakClient.client.createAuthorizeUrl({
            connectTo: autoConnectIdP || undefined,
            redirectPath,
        });
    };

    return {
        getLoginUrl,
        getAccountUrl: () => `${keycloakClient.getAccountUrl()}`,
    };
}
