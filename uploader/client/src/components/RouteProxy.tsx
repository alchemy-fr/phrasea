import {getCurrentUrl, RouteProxyProps} from '@alchemy/navigation';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {config, keycloakClient} from '../init.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {AuthConstant} from '@alchemy/auth';
import RequireLogin from './RequireLogin.tsx';

export default function RouteProxy({
    component: Component,
    public: isPublic = false,
}: RouteProxyProps) {
    const {isAuthenticated, hasSession} = useAuth();
    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    if (!isPublic && !isAuthenticated) {
        if (!hasSession) {
            if (getCurrentUrl().searchParams.has(AuthConstant.LoggedOutParam)) {
                return <RequireLogin />;
            }

            // eslint-disable-next-line react-hooks/immutability
            document.location.href = getLoginUrl();
        }

        return <FullPageLoader backdrop={false} />;
    }

    return <Component />;
}
