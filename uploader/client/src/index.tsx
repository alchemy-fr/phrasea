import ReactDOM from 'react-dom/client';
import './i18n';
import React from 'react';
import './scss/index.scss';
import {AppProvider, initApp} from '@alchemy/phrasea-framework';
import {oauthClient, keycloakClient, matomo, config} from './init.ts';
import App from './components/App.tsx';

initApp(config);

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <AppProvider
            matomo={matomo}
            config={config}
            oauthClient={oauthClient}
            keycloakClient={keycloakClient}
        >
            <App />
        </AppProvider>
    </React.StrictMode>
);
