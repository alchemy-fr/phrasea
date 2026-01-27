import ReactDOM from 'react-dom/client';
import './i18n';
import React from 'react';
import './scss/index.scss';
import {AppProvider, initApp} from '@alchemy/phrasea-framework';
import {
    oauthClient,
    keycloakClient,
    matomo,
    config,
    apiClient,
} from './init.ts';
import App from './components/App.tsx';
import UploaderUserProvider from './context/UploaderUserProvider.tsx';

initApp(config);

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <AppProvider
            matomo={matomo}
            config={config}
            apiClient={apiClient}
            oauthClient={oauthClient}
            keycloakClient={keycloakClient}
        >
            <UploaderUserProvider>
                <App />
            </UploaderUserProvider>
        </AppProvider>
    </React.StrictMode>
);
