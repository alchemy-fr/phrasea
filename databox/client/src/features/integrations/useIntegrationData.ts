import {useMemo} from 'react';
import {useInfiniteQuery} from '@tanstack/react-query';
import {
    getIntegrationData,
    IntegrationObjectType,
} from '@/lib/api/integrations';
import type {IntegrationData} from '@/types/api';

/**
 * The data an integration stored on an object, page by page (`loadMore`).
 * Its query key starts with `['integration-data', integrationId, objectId]`,
 * which is what to invalidate.
 */
export function useIntegrationData(
    integrationId: string,
    object: {type: IntegrationObjectType; id: string},
    {enabled = true}: {enabled?: boolean} = {}
) {
    const query = useInfiniteQuery({
        queryKey: ['integration-data', integrationId, object.id, 'pages'],
        queryFn: ({pageParam, signal}) =>
            getIntegrationData(integrationId, object, signal, pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled,
    });
    const {data, hasNextPage, isFetchingNextPage, fetchNextPage} = query;

    const items = useMemo(() => {
        const byId = new Map<string, IntegrationData>();
        data?.pages.forEach(p => p.items.forEach(d => byId.set(d.id, d)));

        return [...byId.values()];
    }, [data]);

    return {
        items,
        isLoading: query.isLoading,
        hasMore: !!hasNextPage,
        loadingMore: isFetchingNextPage,
        loadMore: () => void fetchNextPage(),
    };
}
