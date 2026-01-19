import {config} from './init.ts';
import {compileRoutes} from '@alchemy/navigation';
import PublicationPage from './pages/PublicationPage';
import AppAuthorizationCodePage from './pages/AppAuthorizationCodePage';
import {NotFoundPage} from '@alchemy/phrasea-ui';
import EmbeddedAssetPage from './pages/EmbeddedAssetPage';
import IndexPage from './pages/IndexPage.tsx';
import PublicationEditPage from './pages/PublicationEditPage.tsx';

const routes = {
    index: {
        public: true,
        path: '/',
        component: NotFoundPage,
    },
    publicationView: {
        public: true,
        path: ':id',
        component: PublicationPage,
        routes: {
            asset: {
                path: ':assetId',
                component: PublicationPage,
            },
        },
    },
    publication: {
        path: 'publications/:id',
        public: false,
        routes: {
            edit: {
                path: 'edit',
                component: PublicationEditPage,
            },
        },
    },
    embedAsset: {
        public: true,
        path: 'embed/:assetId',
        component: EmbeddedAssetPage,
    },
    auth: {
        path: 'auth',
        public: true,
        component: AppAuthorizationCodePage,
    },
};

if (!config.disableIndexPage) {
    routes.index.component = IndexPage;
}

const compiledRoutes = compileRoutes(routes) as typeof routes;
export {compiledRoutes as routes};
