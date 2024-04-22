import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import './i18n';
import Root from './Root.tsx';
import {DashboardMenu} from '@alchemy/react-ps';
import config from './config';
import {initSentry} from '@alchemy/core';

initSentry(config);

ReactDOM.createRoot(document.getElementById('root')!).render(
    <>
        {config.displayServicesMenu && (
            <DashboardMenu dashboardBaseUrl={config.dashboardBaseUrl} />
        )}
        <Root />
    </>
);
