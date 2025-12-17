import React from 'react';
import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import Root from './components/Root';
import './config';
import './i18n';
import './lib/leaflet';
import {AppProvider} from '@alchemy/phrasea-framework';
import {oauthClient, keycloakClient, matomo, config} from './init.ts';

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <AppProvider
            config={config}
            matomo={matomo}
            oauthClient={oauthClient}
            keycloakClient={keycloakClient}
        >
            <Root />
        </AppProvider>
    </React.StrictMode>
);
