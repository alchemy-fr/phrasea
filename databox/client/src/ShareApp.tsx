import {
    DehydratedState,
    HydrationBoundary,
    QueryClient,
    QueryClientProvider,
} from '@tanstack/react-query';
// Deep import: the framework barrel pulls browser-only analytics deps
// that break server-side rendering
import {AppGlobalTheme} from '@alchemy/phrasea-framework/src/Theme/AppGlobalTheme';
import ShareView from './components/Share/ShareView.tsx';

type Props = {
    id: string;
    token: string;
    queryClient: QueryClient;
    dehydratedState?: DehydratedState | undefined;
};

/**
 * Standalone tree for the public share page.
 * Rendered identically by the SSR server (entry-server) and on
 * hydration (index.tsx), so it must stay free of browser-only providers.
 */
export default function ShareApp({
    id,
    token,
    queryClient,
    dehydratedState,
}: Props) {
    return (
        <QueryClientProvider client={queryClient}>
            <HydrationBoundary state={dehydratedState}>
                <AppGlobalTheme>
                    <ShareView id={id} token={token} />
                </AppGlobalTheme>
            </HydrationBoundary>
        </QueryClientProvider>
    );
}

const shareRouteRe = /^\/s\/([^/]+)\/([^/?#]+)/;

export function matchSharePath(
    pathname: string
): {id: string; token: string} | null {
    const m = pathname.match(shareRouteRe);
    if (!m) {
        return null;
    }

    return {id: m[1], token: m[2]};
}
