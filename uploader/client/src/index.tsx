import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import './i18n';
import Root from './Root.tsx';
import config from './config';
import {initSentry} from '@alchemy/core';
import React from 'react';
import {AppGlobalTheme} from '@alchemy/phrasea-framework';

initSentry(config);

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <AppGlobalTheme />
        <Root />
    </React.StrictMode>
);
