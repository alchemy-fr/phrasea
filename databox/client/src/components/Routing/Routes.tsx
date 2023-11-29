import {Route, Routes as BaseRoutes} from 'react-router-dom';
import {RouteDefinition, routes} from '../../routes';
import createRoute from './router';
import NotFound from '../../pages/NotFound';

export default function Routes() {
    return (
        <>
            <BaseRoutes>
                {routes.map((route: RouteDefinition, index: number) =>
                    createRoute(route, index.toString())
                )}
                <Route path={'*'} element={<NotFound />}></Route>
            </BaseRoutes>
        </>
    );
}
