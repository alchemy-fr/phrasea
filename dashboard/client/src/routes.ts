import {compileRoutes} from '@alchemy/navigation';
import AppAuthorizationCodePage from './pages/AppAuthorizationCodePage';
import {AuthConstant} from '@alchemy/auth';
import App from './App.tsx';

const routes = {
    index: {
        path: '/',
        component: App,
        public: true,
    },
    auth: {
        path: AuthConstant.DefaultCheckCodePath,
        component: AppAuthorizationCodePage,
        public: true,
    },
};

const compiledRoutes = compileRoutes(routes) as typeof routes;
export {compiledRoutes as routes};
