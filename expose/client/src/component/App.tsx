import {DashboardMenu} from '@alchemy/react-ps';
import config from '../lib/config';
import {RouterProvider} from '@alchemy/navigation';
import {routes} from '../routes.ts';
import RouteProxy from './RouteProxy.tsx';

type Props = {};

export default function App({}: Props) {
    const css = config.globalCSS;

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
                }}
            />
        </>
    );
}
