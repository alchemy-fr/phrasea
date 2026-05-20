import {useAuth} from './useAuth';
import {UseKeycloakUrlProps, useKeycloakUrls} from './useKeycloakUrls';

export function useForceLogin(props: UseKeycloakUrlProps): void {
    const {isAuthenticated} = useAuth();

    const {getLoginUrl} = useKeycloakUrls(props);

    if (!isAuthenticated) {
        // eslint-disable-next-line react-hooks/immutability
        document.location.href = getLoginUrl();
    }
}
