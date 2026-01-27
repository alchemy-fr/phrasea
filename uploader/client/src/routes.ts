import {compileRoutes} from '@alchemy/navigation';
import AppAuthorizationCodePage from './pages/AppAuthorizationCodePage';
import TargetListPage from './pages/TargetListPage.tsx';
import DownloadPage from './pages/DownloadPage.tsx';
import UploadPage from './pages/UploadPage.tsx';
import FormSchemaIndex from './pages/FormSchemaIndexPage.tsx';
import FormSchemaEditPage from './pages/FormSchemaEditPage.tsx';
import TargetParamIndexPage from './pages/TargetParamIndexPage.tsx';
import TargetParamEditPage from './pages/TargetParamEditPage.tsx';

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
            formSchema: {
                path: 'form-schemas',
                routes: {
                    index: {
                        path: '',
                        component: FormSchemaIndex,
                    },
                    edit: {
                        path: ':id/edit',
                        component: FormSchemaEditPage,
                    },
                    create: {
                        path: 'create',
                        component: FormSchemaEditPage,
                    },
                },
            },
            targetParam: {
                path: 'target-param',
                routes: {
                    index: {
                        path: '',
                        component: TargetParamIndexPage,
                    },
                    edit: {
                        path: ':id/edit',
                        component: TargetParamEditPage,
                    },
                    create: {
                        path: 'create',
                        component: TargetParamEditPage,
                    },
                },
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
