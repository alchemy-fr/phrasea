import {compileRoutes} from '@alchemy/navigation';
import AppAuthorizationCodePage from './pages/AppAuthorizationCodePage';
import TargetListPage from './pages/TargetListPage.tsx';
import DownloadPage from './pages/DownloadPage.tsx';
import UploadPage from './pages/UploadPage.tsx';

const routes = {
    index: {
        path: '/',
        component: TargetListPage,
    },
    upload: {
        path: 'upload/:id',
        component: UploadPage,
    },
    download: {
        path: 'download/:id',
        component: DownloadPage,
    },
    admin: {
        path: 'admin',
        routes: {
            formEditor: {
                path: 'form-editor',
                // component: FormEditor as unknown as React.FC,
            },
            targetDataEditor: {
                path: 'target-data-editor',
                // component: TargetDataEditor as unknown as React.FC,
            },
        },
    },
    auth: {
        path: 'auth',
        component: AppAuthorizationCodePage,
        public: true,
    },
};

const compiledRoutes = compileRoutes(routes) as typeof routes;
export {compiledRoutes as routes};
