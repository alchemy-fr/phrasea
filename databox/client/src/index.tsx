import React from 'react';
import ReactDOM from 'react-dom/client';
import './scss/index.scss';
import Root from './components/Root';
import './i18n';
import './lib/leaflet';
import ShareApp, {matchSharePath} from './ShareApp.tsx';
import {queryClient} from './lib/query.ts';
import type {DehydratedState} from '@tanstack/react-query';

const container = document.getElementById('root')!;

const dehydratedState = (
    window as unknown as {
        __REACT_QUERY_STATE__?: DehydratedState;
    }
).__REACT_QUERY_STATE__;
const shareMatch = matchSharePath(document.location.pathname);

if (dehydratedState && shareMatch) {
    // The share page was server-side rendered: hydrate the same standalone tree
    ReactDOM.hydrateRoot(
        container,
        <React.StrictMode>
            <ShareApp
                id={shareMatch.id}
                token={shareMatch.token}
                queryClient={queryClient}
                dehydratedState={dehydratedState}
            />
        </React.StrictMode>
    );
} else {
    ReactDOM.createRoot(container).render(
        <React.StrictMode>
            <Root />
        </React.StrictMode>
    );
}
