import {useAuth} from "./useAuth";
import {UseKeycloakUrlProps, useKeycloakUrls} from "./useKeycloakUrls";

export function useForceLogin(props: UseKeycloakUrlProps): void {
    const {isAuthenticated} = useAuth();

    const {getLoginUrl} = useKeycloakUrls(props);

    if (!isAuthenticated()) {
        document.location.href = getLoginUrl();
    }
}
