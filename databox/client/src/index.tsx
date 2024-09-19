import React from 'react';
import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import Root from './components/Root';
import './config';
import './i18n';
import './lib/leaflet';
import {initSentry} from '@alchemy/core';
import config from './config';
import {QueryClient, QueryClientProvider} from "@tanstack/react-query";

initSentry(config);

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 1000 * 60 * 5,
        }
    }
});

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <QueryClientProvider client={queryClient}>
        <Root />
        </QueryClientProvider>
    </React.StrictMode>
);
