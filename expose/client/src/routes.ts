import config from './lib/config.ts';
import PublicationIndex from './component/index/PublicationIndex.jsx';
import {compileRoutes} from '@alchemy/navigation';
import PublicationPage from './pages/PublicationPage.tsx';
import AssetPage from './pages/AssetPage.tsx';
import AppAuthorizationCodePage from './pages/AppAuthorizationCodePage.tsx';
import {NotFoundPage} from '@alchemy/phrasea-ui';

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
        path: 'embed/:asset',
        component: PublicationPage,
    },
    auth: {
        path: 'auth',
        component: AppAuthorizationCodePage,
        public: true,
    },
};

if (!config.disableIndexPage) {
    routes.index.component = PublicationIndex;
}

const compiledRoutes = compileRoutes(routes) as typeof routes;
export {compiledRoutes as routes};

