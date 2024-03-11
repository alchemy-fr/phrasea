import {DashboardMenu} from '@alchemy/react-ps';
import config from '../config';
import {MatomoRouteWrapper, RouterProvider} from '@alchemy/navigation';
import {setSentryUser} from '@alchemy/core';
import {useAuth} from '@alchemy/react-auth';
import {routes} from '../routes.ts';
import RouteProxy from './RouteProxy.tsx';
import React from 'react';

type Props = {};

export default function App({}: Props) {
    const css = config.globalCSS;
    const {user} = useAuth();

    React.useEffect(() => {
        setSentryUser(user);
    }, [user]);

    return (
        <>
            {css && <style>{css}</style>}
            {config.displayServicesMenu && (
                <DashboardMenu dashboardBaseUrl={config.dashboardBaseUrl} />
            )}
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
