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
import AttributeFormatProvider from './Media/Asset/Attribute/Format/AttributeFormatProvider.tsx';
import { useTranslation } from 'react-i18next';

type Props = {};

export default function Root({}: Props) {
    return (
        <AuthenticationProvider oauthClient={oauthClient}>
            <MatomoUser />

            <AttributeFormatProvider>
                <UserPreferencesProvider>
                    <RouterProvider
                        routes={routes}
                        options={{
                            RouteProxyComponent: RouteProxy,
                            WrapperComponent: WrapperComponent,
                        }}
                    />
                </UserPreferencesProvider>
            </AttributeFormatProvider>
        </AuthenticationProvider>
    );
}

function WrapperComponent({children}: RouteWrapperProps) {
    const {t} = useTranslation();
    return (
        <>
            <ModalStack>
                <SessionExpireContainer />
                <OverlayOutlet
                    routes={modalRoutes}
                    queryParam={t('wrapper_component.m', `_m`)}
                    RouteProxyComponent={RouteProxy}
                />
                <MatomoRouteWrapper>{children}</MatomoRouteWrapper>
            </ModalStack>
        </>
    );
}
