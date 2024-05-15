import {DashboardMenu} from '@alchemy/react-ps';
import config from '../config';
import {MatomoRouteWrapper, RouterProvider} from '@alchemy/navigation';
import {setSentryUser} from '@alchemy/core';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {routes} from '../routes.ts';
import RouteProxy from './RouteProxy.tsx';
import React from 'react';
import Box from "@mui/material/Box";
import {UserMenu} from '@alchemy/phrasea-ui';
import {keycloakClient} from "../lib/api-client.ts";

type Props = {};

export default function App({}: Props) {
    const css = config.globalCSS;
    const {user, logout} = useAuth();

    const {getAccountUrl} = useKeycloakUrls({
        autoConnectIdP: config.autoConnectIdP,
        keycloakClient,
    });


    React.useEffect(() => {
        setSentryUser(user);
    }, [user]);

    return (
        <>
            {css && <style>{css}</style>}
            <Box sx={theme => ({
                position: 'absolute',
                zIndex: 1000,
                top: theme.spacing(1),
                right: theme.spacing(1),
                '.services-menu + button': {
                    ml: 1,
                }
            })}>
                {config.displayServicesMenu && (
                    <DashboardMenu
                        dashboardBaseUrl={config.dashboardBaseUrl}
                        style={{
                            position: 'static',
                            display: 'inline-block',
                        }}
                    />
                )}
                {user ? (
                    <UserMenu
                        menuHeight={50}
                        username={user?.username}
                        accountUrl={getAccountUrl()}
                        onLogout={logout}
                    />
                ) : ''}
            </Box>
            <RouterProvider
                routes={routes}
                options={{
                    RouteProxyComponent: RouteProxy,
                    WrapperComponent: MatomoRouteWrapper,
                }}
            />
        </>
    );
}
