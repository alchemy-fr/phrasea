'use client';

import {useEffect} from 'react';
import {useRouter} from 'next/navigation';
import {useQuery} from '@tanstack/react-query';
import {getPageBySlug} from '@/lib/api/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {isApiError} from '@/lib/api/http';
import {routes} from '@/lib/routes';
import {PageRenderer} from './PageRenderer';
import {AppShell} from '@/components/layout/AppShell';

/**
 * Home: the CMS page with an empty slug when it exists, otherwise the
 * assets screen.
 */
export function HomeScreen() {
    const router = useRouter();
    const page = useQuery({
        queryKey: ['page-by-slug', ''],
        queryFn: () => getPageBySlug('-'),
        retry: false,
    });

    useEffect(() => {
        if (page.isError) {
            router.replace(routes.assets());
        }
    }, [page.isError, router]);

    if (page.isLoading || page.isError) {
        return <FullPageLoader />;
    }
    if (!page.data || !page.data.enabled) {
        if (!isApiError(page.error)) {
            router.replace(routes.assets());
        }

        return <FullPageLoader />;
    }

    return (
        <AppShell>
            <PageRenderer page={page.data} />
        </AppShell>
    );
}
