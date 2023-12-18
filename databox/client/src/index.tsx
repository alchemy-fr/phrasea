import React from 'react';
import ReactDOM from 'react-dom/client';
import reportWebVitals from './reportWebVitals';
import './scss/index.scss';
import Root from './components/Root';
import './config';
import './i18n';
import './lib/leaflet';
import {initSentry} from '@alchemy/core'
import config from "./config.ts";

initSentry(config);

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <Root/>
    </React.StrictMode>
);

// If you want to start measuring performance in your app, pass a function
// to log results (for example: reportWebVitals(console.log))
// or send to an analytics endpoint. Learn more: https://bit.ly/CRA-vitals
reportWebVitals();
