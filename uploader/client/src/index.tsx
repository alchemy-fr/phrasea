import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import './locales/i18n';
import Root from './Root.tsx';
import React from 'react';
import {DashboardMenu} from '@alchemy/react-ps';
import config from "./config.ts";

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        {config.displayServicesMenu && (
            <DashboardMenu dashboardBaseUrl={config.dashboardBaseUrl} />
        )}
        <Root />
    </React.StrictMode>
);
