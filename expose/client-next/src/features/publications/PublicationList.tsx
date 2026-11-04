'use client';

import {useTranslation} from 'react-i18next';
import {useInfiniteQuery} from '@tanstack/react-query';
import {BookImageIcon} from 'lucide-react';
import {
    getPublications,
    publicationKeys,
    type PublicationSort,
} from '@/lib/api/publications';
import {Button} from '@/components/ui/button';
import {EmptyState, Skeleton} from '@/components/ui/misc';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {ErrorScreen} from '@/components/ErrorScreen';
import {useStoredState} from '@/hooks/useStoredState';
import {PublicationCard} from './PublicationCard';

/** The public home page: the root publications the user may see */
export function PublicationList() {
    const {t} = useTranslation();
    const [sort, setSort] = useStoredState<PublicationSort>(
        'expose.publications.sort',
        'recent',
        v => v === 'recent' || v === 'name'
    );
    const params = {sort};
    const query = useInfiniteQuery({
        queryKey: publicationKeys.list(params),
        queryFn: ({pageParam}) => getPublications(params, pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const publications = query.data?.pages.flatMap(p => p.items) ?? [];

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold tracking-tight">
                    {t('publications.title', 'Publications')}
                </h1>
                <div className="flex items-center gap-2 text-sm">
                    <span className="text-muted-foreground">
                        {t('publications.sort_by', 'Sort by')}
                    </span>
                    <Select
                        value={sort}
                        onValueChange={v => setSort(v as PublicationSort)}
                    >
                        <SelectTrigger
                            size="sm"
                            data-testid="publications-sort"
                            aria-label={t('publications.sort_by', 'Sort by')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="recent">
                                {t('publications.sort.recent', 'Most recent')}
                            </SelectItem>
                            <SelectItem value="name">
                                {t('publications.sort.name', 'Name')}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            {query.isPending ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                    {Array.from({length: 6}, (_, i) => (
                        <Skeleton key={i} className="h-72 rounded-xl" />
                    ))}
                </div>
            ) : query.isError ? (
                <ErrorScreen
                    error={query.error}
                    onRetry={() => query.refetch()}
                />
            ) : publications.length === 0 ? (
                <EmptyState
                    testId="no-publication"
                    icon={<BookImageIcon />}
                    title={t('publications.empty', 'No publication')}
                />
            ) : (
                <ul
                    data-testid="publication-list"
                    className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3"
                >
                    {publications.map(publication => (
                        <li key={publication.id}>
                            <PublicationCard publication={publication} />
                        </li>
                    ))}
                </ul>
            )}
            {query.hasNextPage ? (
                <div className="flex justify-center">
                    <Button
                        variant="outline"
                        data-testid="load-more"
                        loading={query.isFetchingNextPage}
                        onClick={() => query.fetchNextPage()}
                    >
                        {t('common.load_more', 'Load more')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
