import App from './components/App';
import WorkspaceDialog from './components/Dialog/Workspace/WorkspaceDialog';
import CollectionDialog from './components/Dialog/Collection/CollectionDialog';
import AssetDialog from './components/Dialog/Asset/AssetDialog';
import AssetView from './components/Media/Asset/View/AssetView.tsx';
import WorkflowView from './components/Workflow/WorkflowView';
import AppAuthorizationCodePage from './components/AppAuthorizationCodePage';
import {compileRoutes} from '@alchemy/navigation';
import BasketDialog from './components/Dialog/Basket/BasketDialog';
import BasketViewDialog from './components/Basket/BasketViewDialog';
import AttributeEditorView from './components/AttributeEditor/AttributeEditorView.tsx';
import SharePage from './pages/SharePage.tsx';
import AttributeListListDialog from "./components/AttributeList/AttributeListsDialog.tsx";
import AttributeListDialog from "./components/Dialog/AttributeList/AttributeListDialog.tsx";

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
    baskets: {
        public: false,
        path: '/baskets/:id',
        routes: {
            manage: {
                path: 'manage/:tab',
                component: BasketDialog,
            },
            view: {
                path: 'view',
                component: BasketViewDialog,
            },
        },
    },
    attributeList: {
        public: false,
        path: '/attribute-lists/:id',
        routes: {
            manage: {
                path: 'manage/:tab',
                component: AttributeListDialog,
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
            viewGuessRendition: {
                path: '',
                component: AssetView,
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
    attributesBatchEdit: {
        path: '/attributes/editor',
        component: AttributeEditorView,
        public: false,
    },
};

const routes = {
    app: {
        path: '/',
        component: App,
        public: true,
    },
    auth: {
        path: '/auth',
        component: AppAuthorizationCodePage,
        public: true,
    },
    share: {
        path: '/s/:id/:token',
        component: SharePage,
        public: true,
    },
};

const compiledRoutes = compileRoutes(routes) as typeof routes;
export {compiledRoutes as routes};

const compiledModalRoutes = compileRoutes(modalRoutes) as typeof modalRoutes;
export {compiledModalRoutes as modalRoutes};
