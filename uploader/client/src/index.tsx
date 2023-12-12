import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import './locales/i18n';
import Root from './Root.tsx';
import React from 'react';

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <Root />
    </React.StrictMode>
);
