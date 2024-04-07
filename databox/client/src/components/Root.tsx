import {
    MatomoRouteWrapper,
    ModalStack,
    OverlayOutlet,
    RouterProvider,
    RouteWrapperProps,
} from '@alchemy/navigation';
import UserPreferencesProvider from './User/Preferences/UserPreferencesProvider';
import {oauthClient} from '../api/api-client';
import {
    AuthenticationProvider,
    MatomoUser,
    SessionExpireContainer,
} from '@alchemy/react-auth';
import {modalRoutes, routes} from '../routes';
import RouteProxy from './Routing/RouteProxy';

type Props = {};

export default function Root({}: Props) {
    return (
        <AuthenticationProvider oauthClient={oauthClient}>
            <MatomoUser />
            <UserPreferencesProvider>
                <RouterProvider
                    routes={routes}
                    options={{
                        RouteProxyComponent: RouteProxy,
                        WrapperComponent: WrapperComponent,
                    }}
                />
            </UserPreferencesProvider>
        </AuthenticationProvider>
    );
}

function WrapperComponent({children}: RouteWrapperProps) {
    return (
        <>
            <ModalStack>
                <SessionExpireContainer />
                <OverlayOutlet routes={modalRoutes} queryParam={'_m'} />
                <MatomoRouteWrapper>{children}</MatomoRouteWrapper>
            </ModalStack>
        </>
    );
}
