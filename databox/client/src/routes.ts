import App from './components/App';
import WorkspaceDialog from './components/Dialog/Workspace/WorkspaceDialog';
import CollectionDialog from './components/Dialog/Collection/CollectionDialog';
import AssetDialog from './components/Dialog/Asset/AssetDialog';
import AssetView from './components/Media/Asset/AssetView';
import WorkflowView from './components/Workflow/WorkflowView';
import AppAuthorizationCodePage from './components/AppAuthorizationCodePage.tsx';
import {compileRoutes} from '@alchemy/navigation';

const modalRoutes = {
    workspaces: {
        public: false,
        path: '/workspaces/:id',
        routes: {
            manage: {
                path: 'manage/:tab',
                component: WorkspaceDialog,
            },
        },
    },
    collections: {
        public: false,
        path: '/collections/:id',
        routes: {
            manage: {
                path: 'manage/:tab',
                component: CollectionDialog,
            },
        },
    },
    assets: {
        public: false,
        path: '/assets/:id',
        routes: {
            manage: {
                path: 'manage/:tab',
                component: AssetDialog,
            },
            view: {
                path: ':renditionId',
                component: AssetView,
            },
        },
    },
    workflow: {
        path: '/workflows/:id',
        component: WorkflowView,
        public: false,
    },
};

const routes = {
    app: {
        path: '/',
        component: App,
        routes: modalRoutes,
        public: true,
    },
    auth: {
        path: '/auth',
        component: AppAuthorizationCodePage,
        public: true,
    },
};

const compiledRoutes = compileRoutes(routes) as typeof routes;
export {compiledRoutes as routes};

const compiledModalRoutes = compileRoutes(modalRoutes) as typeof modalRoutes;
export {compiledModalRoutes as modalRoutes};
