import React from 'react';
import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import Root from './components/Root';
import './config';
import './i18n';
import './lib/leaflet';
import {AppProvider} from '@alchemy/phrasea-framework';
import {config, keycloakClient, matomo, oauthClient} from './init.ts';
import UserPreferencesProvider from './components/User/Preferences/UserPreferencesProvider.tsx';

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <UserPreferencesProvider>
            <AppProvider
                config={config}
                matomo={matomo}
                oauthClient={oauthClient}
                keycloakClient={keycloakClient}
            >
                <Root />
            </AppProvider>
        </UserPreferencesProvider>
    </React.StrictMode>
);
