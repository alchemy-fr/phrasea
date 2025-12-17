import {config} from './init.ts';
import {compileRoutes} from '@alchemy/navigation';
import PublicationPage from './pages/PublicationPage';
import AssetPage from './pages/AssetPage';
import AppAuthorizationCodePage from './pages/AppAuthorizationCodePage';
import {NotFoundPage} from '@alchemy/phrasea-ui';
import EmbeddedAssetPage from './pages/EmbeddedAssetPage';
import IndexPage from './pages/IndexPage.tsx';

const routes = {
    index: {
        path: '/',
        component: NotFoundPage,
    },
    publication: {
        path: ':id',
        component: PublicationPage,
        routes: {
            asset: {
                path: ':assetId',
                component: AssetPage,
            },
        },
    },
    embedAsset: {
        path: 'embed/:assetId',
        component: EmbeddedAssetPage,
    },
    auth: {
        path: 'auth',
        component: AppAuthorizationCodePage,
        public: true,
    },
};

if (!config.disableIndexPage) {
    routes.index.component = IndexPage;
}

const compiledRoutes = compileRoutes(routes) as typeof routes;
export {compiledRoutes as routes};
