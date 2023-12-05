import {AuthenticationProvider} from '@alchemy/auth';
import {DashboardMenu} from '@alchemy/react-ps';
import {oauthClient} from '../lib/api-client';
import config from '../lib/config';
import RouterProvider from '@alchemy/navigation/src/RouterProvider.tsx';
import {routes} from '../routes.ts';
import {MatomoRouteProxy} from '@alchemy/navigation';

type Props = {};

export default function App({}: Props) {
    const css = config.globalCSS;

    return <AuthenticationProvider oauthClient={oauthClient}>
        {css && <style>{css}</style>}
        {config.displayServicesMenu && (
            <DashboardMenu dashboardBaseUrl={config.dashboardBaseUrl}/>
        )}
        <RouterProvider
            routes={routes}
            RouteProxyComponent={MatomoRouteProxy}
        />
    </AuthenticationProvider>
}
