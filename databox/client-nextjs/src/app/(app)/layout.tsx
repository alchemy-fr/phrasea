import {Suspense, type ReactNode} from 'react';
import {AppShell} from '@/components/layout/AppShell';
import {RouteHistoryProvider} from '@/components/modals/RouteDialog';
import {SearchProvider} from '@/features/search/SearchProvider';
import {ResultProvider} from '@/features/search/ResultProvider';

/**
 * The search state (URL) and results are shared by the search screen and the
 * persistent left panel (facets, tree, baskets): the providers live above the
 * shell so that panel actions work from any route.
 */
export default function AppLayout({
    children,
    modal,
}: Readonly<{children: ReactNode; modal: ReactNode}>) {
    return (
        <Suspense>
            <SearchProvider>
                <ResultProvider>
                    <RouteHistoryProvider>
                        <AppShell>
                            {children}
                            {modal}
                        </AppShell>
                    </RouteHistoryProvider>
                </ResultProvider>
            </SearchProvider>
        </Suspense>
    );
}
