import {MatomoRouteWrapper, RouterProvider} from '@alchemy/navigation';
import {routes} from './routes';
import './scss/App.scss';
import Menu from './components/Menu';
import RouteProxy from './components/RouteProxy';
import React, {PropsWithChildren} from 'react';
import {setSentryUser} from '@alchemy/core';
import {useAuth} from '@alchemy/react-auth';

type Props = {};

export default function App({}: Props) {
    const {user} = useAuth();

    React.useEffect(() => {
        setSentryUser(user);
    }, [user]);

    return (
        <RouterProvider
            routes={routes}
            options={{
                RouteProxyComponent: RouteProxy,
                WrapperComponent: Wrapper,
            }}
        />
    );
}

function Wrapper({children}: PropsWithChildren<{}>) {
    return (
        <MatomoRouteWrapper>
            <Menu>{children}</Menu>
        </MatomoRouteWrapper>
    );
}
