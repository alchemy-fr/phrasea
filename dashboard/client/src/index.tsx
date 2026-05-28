import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import './i18n';
import React from 'react';
import {
    AppProvider,
    initApp,
    MatomoRouteWrapper,
} from '@alchemy/phrasea-framework';
import {
    apiClient,
    config,
    keycloakClient,
    matomo,
    oauthClient,
} from './init.ts';
import {RouterProvider, RouteWrapperProps} from '@alchemy/navigation';
import {routes} from './routes.ts';
import RouteProxy from './components/RouteProxy.tsx';
import DashboardBar from './DashboardBar.tsx';
import {Container} from '@mui/material';
import {useIsLarge} from './hooks/useIsLarge.ts';

initApp(config);

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <AppProvider
            matomo={matomo}
            config={config}
            oauthClient={oauthClient}
            apiClient={apiClient}
            keycloakClient={keycloakClient}
        >
            <RouterProvider
                routes={routes}
                options={{
                    RouteProxyComponent: RouteProxy,
                    WrapperComponent: WrapperComponent,
                }}
            />
        </AppProvider>
    </React.StrictMode>
);

function WrapperComponent({children}: RouteWrapperProps) {
    const isLarge = useIsLarge();

    return (
        <>
            <MatomoRouteWrapper>
                <Container>
                    {isLarge && <DashboardBar />}
                    {children}
                </Container>
            </MatomoRouteWrapper>
        </>
    );
}
