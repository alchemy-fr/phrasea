import {RouterProvider} from '@alchemy/navigation';
import {routes} from './routes';
import './scss/App.scss';
import Menu from './components/Menu';
import RouteProxy from './components/RouteProxy';

type Props = {};

export default function App({}: Props) {
    return (
        <RouterProvider
            routes={routes}
            options={{
                RouteProxyComponent: RouteProxy,
                WrapperComponent: Menu,
            }}
        />
    );
}
