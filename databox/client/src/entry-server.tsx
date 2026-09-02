import React from 'react';
import {renderToString} from 'react-dom/server';
import createCache from '@emotion/cache';
import {CacheProvider} from '@emotion/react';
import createEmotionServer from '@emotion/server/create-instance';
import {dehydrate, QueryClient} from '@tanstack/react-query';
import './i18n';
import ShareApp, {matchSharePath} from './ShareApp.tsx';
import {getPublicShare} from './api/asset.ts';
import type {Share} from './types.ts';

export type SsrResult = {
    html: string;
    styleTags: string;
    headTags: string;
    dehydratedState: unknown;
};

/**
 * Server-side renders the given URL, or returns null when the route
 * is not server-rendered (the SPA shell is served as usual).
 * Only the public share page is server-rendered for now.
 */
export async function render(url: string): Promise<SsrResult | null> {
    const pathname = url.split('?')[0];
    const shareMatch = matchSharePath(pathname);
    if (!shareMatch) {
        return null;
    }

    const {id, token} = shareMatch;

    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 1000 * 60 * 5,
            },
        },
    });

    await queryClient.prefetchQuery({
        queryKey: ['share', id, token],
        queryFn: () => getPublicShare(id, token),
    });

    const share = queryClient.getQueryData<Share>(['share', id, token]);
    if (!share) {
        // Fetch failed (bad token, expired share, API down…):
        // let the SPA handle the route and display its own error state.
        return null;
    }

    const cache = createCache({key: 'css'});
    const emotionServer = createEmotionServer(cache);

    const dehydratedState = dehydrate(queryClient);

    const html = renderToString(
        <CacheProvider value={cache}>
            <ShareApp
                id={id}
                token={token}
                queryClient={queryClient}
                dehydratedState={dehydratedState}
            />
        </CacheProvider>
    );

    const styleTags = emotionServer.constructStyleTagsFromChunks(
        emotionServer.extractCriticalToChunks(html)
    );

    return {
        html,
        styleTags,
        headTags: buildHeadTags(share),
        dehydratedState,
    };
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function buildHeadTags(share: Share): string {
    const title =
        share.name ||
        (share.assets ?? [])
            .map(a => a.name)
            .filter(Boolean)
            .join(', ') ||
        'Shared assets';

    const tags: string[] = [
        `<title>${escapeHtml(title)}</title>`,
        `<meta property="og:title" content="${escapeHtml(title)}"/>`,
        `<meta property="og:type" content="website"/>`,
    ];

    const thumbnail = (share.assets ?? [])
        .map(a => a.thumbnail?.file?.url ?? a.preview?.file?.url)
        .find(Boolean);
    if (thumbnail) {
        tags.push(
            `<meta property="og:image" content="${escapeHtml(thumbnail)}"/>`
        );
    }

    return tags.join('\n    ');
}
