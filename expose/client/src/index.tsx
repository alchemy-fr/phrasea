import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import ConfigWrapper from './component/ConfigWrapper';
import './i18n/i18n';
import AnalyticsProvider from './component/anaytics/AnalyticsProvider';
import React from 'react';
import config from './config';
import {initSentry} from '@alchemy/core';

initSentry(config);

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <AnalyticsProvider>
            <ConfigWrapper />
        </AnalyticsProvider>
    </React.StrictMode>
);
