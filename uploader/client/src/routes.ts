import {compileRoutes} from '@alchemy/navigation';
import AppAuthorizationCodePage from './pages/AppAuthorizationCodePage';
import SelectTarget from './components/page/SelectTarget';
import Download from './components/page/Download';
import FormEditor from './components/page/FormEditor.jsx';
import TargetDataEditor from './components/page/TargetDataEditor.jsx';
import React from 'react';
import UploadPage from "./components/page/UploadPage.tsx";

const routes = {
    index: {
        path: '/',
        component: SelectTarget,
    },
    upload: {
        path: 'upload/:id',
        component: UploadPage,
    },
    download: {
        path: 'download/:id',
        component: Download,
    },
    admin: {
        path: 'admin',
        routes: {
            formEditor: {
                path: 'form-editor',
                component: FormEditor as unknown as React.FC,
            },
            targetDataEditor: {
                path: 'target-data-editor',
                component: TargetDataEditor as unknown as React.FC,
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
