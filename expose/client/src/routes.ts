import AuthorizationCodePage from "@alchemy/auth/src/components/AuthorizationCodePage.tsx";
import config from "./lib/config.ts";
import PublicationIndex from "./component/index/PublicationIndex.jsx";
import {RouteDefinition, Routes} from "@alchemy/navigation";
import PublicationPage from "./pages/PublicationPage.tsx";
import AssetPage from "./pages/AssetPage.tsx";

const routes: Routes = {
    index: {
        path: '/auth',
        component: AuthorizationCodePage
    },
    publication: {
        path: '/:id',
        component: PublicationPage,
        routes: {
            asset: {
                path: '/:assetId',
                component: AssetPage,
            }
        }
    },
    embedAsset: {
        path: 'embed/:asset',
        component: PublicationPage,
    },
}

if (!config.disableIndexPage) {
    routes['index'] = {
        path: '/',
        component: PublicationIndex,
    } as RouteDefinition;
}

export {routes};
