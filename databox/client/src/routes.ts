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
import ProfileDialog from './components/Dialog/Profile/ProfileDialog.tsx';
import FileDialog from './components/Dialog/File/FileDialog.tsx';
import SavedSearchDialog from './components/Dialog/SavedSearch/SavedSearchDialog.tsx';
import HomePage from './pages/HomePage.tsx';
import PageEditPage from './pages/PageEditPage.tsx';
import PagePage from './pages/PagePage.tsx';
import PageIndexPage from './pages/PageIndexPage.tsx';
import {AuthConstant} from '@alchemy/auth';
import OperationTasksDialog from './components/OperationTasks/OperationTasksDialog.tsx';
import RunTaskDialog from './components/OperationTasks/RunTaskDialog.tsx';
import TasksListDialog from './components/OperationTasks/TasksListDialog.tsx';

export enum Routing {
    UnknownRendition = '_',
}

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
    savedSearch: {
        public: false,
        path: '/saved-searches/:id',
        routes: {
            manage: {
                path: 'manage/:tab',
                component: SavedSearchDialog,
            },
        },
    },
    profiles: {
        public: false,
        path: '/profiles/:id',
        routes: {
            manage: {
                path: 'manage/:tab',
                component: ProfileDialog,
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
                public: true,
                path: ':renditionId',
                component: AssetView,
            },
        },
    },
    files: {
        public: false,
        path: '/files/:id',
        routes: {
            manage: {
                path: 'manage/:tab',
                component: FileDialog,
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
    operationTasks: {
        path: '/admin/tasks',
        public: false,
        routes: {
            index: {
                component: TasksListDialog,
                path: '',
            },
            create: {
                path: 'new',
                component: OperationTasksDialog,
            },
            task: {
                component: RunTaskDialog,
                path: ':task/run',
            },
        },
    },
};

const routes = {
    home: {
        path: '/',
        component: HomePage,
        public: true,
    },
    pages: {
        path: 'p/:slug',
        component: PagePage,
        public: true,
    },
    pageAdmin: {
        path: 'pages',
        routes: {
            index: {
                path: '',
                component: PageIndexPage,
                public: false,
            },
            edit: {
                path: ':id/edit',
                component: PageEditPage,
                public: false,
            },
        },
    },
    assets: {
        path: 'assets',
        component: App,
        public: true,
    },
    auth: {
        path: AuthConstant.DefaultCheckCodePath,
        component: AppAuthorizationCodePage,
        public: true,
    },
    share: {
        path: 's/:id/:token',
        component: SharePage,
        public: true,
    },
};

const compiledRoutes = compileRoutes(routes) as typeof routes;
export {compiledRoutes as routes};

const compiledModalRoutes = compileRoutes(modalRoutes) as typeof modalRoutes;
export {compiledModalRoutes as modalRoutes};
