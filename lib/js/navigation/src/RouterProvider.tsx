import {createBrowserRouter, RouterProvider as RouterProviderBase} from "react-router-dom";
import {createRouterProviderRoutes, RouterProviderOptions} from "./Router";
import {Routes} from "./types";

type Props = {
    routes: Routes;
    options: RouterProviderOptions;
};

export default function RouterProvider({
    routes,
    options = {}
}: Props) {
    const router = createBrowserRouter(createRouterProviderRoutes(routes, options));

    return <RouterProviderBase
        router={router}
    />
}
