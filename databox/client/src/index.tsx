import React from 'react';
import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import Root from './components/Root';
import './config';
import './i18n';
import './lib/leaflet';
import {initSentry} from '@alchemy/core';
import config from './config';
import {QueryClientProvider} from '@tanstack/react-query';
import {queryClient} from './lib/query.ts';
import {locales} from '@alchemy/i18n/src/Locale/locales';

initSentry(config);

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <QueryClientProvider client={queryClient}>
            <Root />
        </QueryClientProvider>
    </React.StrictMode>
);

Object.keys(locales).map(l => {
    if (!locales[l].nameLocal) {
        const ref = Object.keys(locales).find(sl => sl.startsWith(l + '_'));
        console.log('ref', ref);
        if (ref) {
            locales[l].nameLocal = locales[ref].nameLocal
                .replace(/\([^)]+\)/, '')
                .trim();
        } else {
            locales[l].nameLocal = locales[l].name;
        }
    }
});

console.log('locales:', locales);
