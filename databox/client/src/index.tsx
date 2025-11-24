import React from 'react';
import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import Root from './components/Root';
import './config';
import './i18n';
import './lib/leaflet';
import AnalyticsProvider from './components/Analytics/AnalyticsProvider';
import {initSentry} from '@alchemy/core';
import config from './config';
import {QueryClientProvider} from '@tanstack/react-query';
import {queryClient} from './lib/query.ts';

initSentry(config);

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <AnalyticsProvider>
            <QueryClientProvider client={queryClient}>
                <Root />
            </QueryClientProvider>
        </AnalyticsProvider>
    </React.StrictMode>
);
