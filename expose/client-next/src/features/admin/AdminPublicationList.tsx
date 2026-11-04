'use client';

import {useState} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery} from '@tanstack/react-query';
import {
    BookImageIcon,
    ExternalLinkIcon,
    EyeOffIcon,
    PencilIcon,
    SearchIcon,
    Trash2Icon,
} from 'lucide-react';
import {getPublications, publicationKeys} from '@/lib/api/publications';
import {Button} from '@alchemy/next-ui/ui/button';
import {Input} from '@alchemy/next-ui/ui/input';
import {Badge, EmptyState, Skeleton} from '@alchemy/next-ui/ui/misc';
import {ErrorScreen} from '@/components/ErrorScreen';
import {useDebouncedValue} from '@alchemy/next-ui/hooks/useDebouncedValue';
import {Thumbnail} from '@/features/publication/Thumbnail';
import {useDeletePublication} from '@/features/publications/usePublicationActions';
import {useTranslateField} from '@/lib/utils/translate';
import {routes} from '@/lib/routes';

/**
 * Every publication the user can edit, sub-publications included, whether
 * listed on the home page or not (which may be turned off).
 */
export function AdminPublicationList() {
    const {t} = useTranslation();
    const translate = useTranslateField();
    const remove = useDeletePublication();
    const [search, setSearch] = useState('');
    const query = useDebouncedValue(search.trim(), 300);
    const params = {
        editable: true,
        flatten: true,
        sort: 'name' as const,
        query,
    };
    const list = useInfiniteQuery({
        queryKey: publicationKeys.list(params),
        queryFn: ({pageParam}) => getPublications(params, pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const publications = list.data?.pages.flatMap(p => p.items) ?? [];

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6 sm:px-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold tracking-tight">
                    {t('admin.publications.title', 'Publications')}
                </h1>
                <div className="relative w-full sm:w-72">
                    <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        data-testid="publications-search"
                        className="pl-8"
                        placeholder={t('common.search', 'Search…')}
                        aria-label={t('common.search', 'Search…')}
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                    />
                </div>
            </div>
            <p className="-mt-3 text-sm text-muted-foreground">
                {t(
                    'admin.publications.help',
                    'Publications are created from Databox; they are edited here.'
                )}
            </p>
            {list.isPending ? (
                <div className="flex flex-col gap-2">
                    {Array.from({length: 5}, (_, i) => (
                        <Skeleton key={i} className="h-16 rounded-lg" />
                    ))}
                </div>
            ) : list.isError ? (
                <ErrorScreen
                    error={list.error}
                    onRetry={() => list.refetch()}
                />
            ) : publications.length === 0 ? (
                <EmptyState
                    testId="no-publication"
                    icon={<BookImageIcon />}
                    title={t('publications.empty', 'No publication')}
                />
            ) : (
                <ul
                    data-testid="admin-publication-list"
                    className="divide-y rounded-lg border bg-card"
                >
                    {publications.map(publication => (
                        <li
                            key={publication.id}
                            data-testid="admin-publication"
                            className="flex items-center gap-3 p-3"
                        >
                            {publication.cover ? (
                                <Thumbnail
                                    asset={publication.cover}
                                    className="size-12 shrink-0 rounded-md [&_svg]:size-5"
                                />
                            ) : (
                                <span className="flex size-12 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                    <BookImageIcon className="size-5" />
                                </span>
                            )}
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link
                                        href={routes.publication(publication)}
                                        className="truncate font-medium hover:underline"
                                    >
                                        {translate(publication, 'title')}
                                    </Link>
                                    {publication.enabled === false ? (
                                        <Badge variant="secondary">
                                            <EyeOffIcon />{' '}
                                            {t(
                                                'admin.publications.disabled',
                                                'Disabled'
                                            )}
                                        </Badge>
                                    ) : null}
                                    {publication.publiclyListed === false ? (
                                        <Badge variant="outline">
                                            {t(
                                                'admin.publications.unlisted',
                                                'Unlisted'
                                            )}
                                        </Badge>
                                    ) : null}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    /{publication.slug || publication.id}
                                </div>
                            </div>
                            <div className="flex shrink-0 gap-1">
                                <Button variant="ghost" size="icon-sm" asChild>
                                    <Link
                                        href={routes.publication(publication)}
                                        aria-label={t('common.open', 'Open')}
                                        title={t('common.open', 'Open')}
                                    >
                                        <ExternalLinkIcon />
                                    </Link>
                                </Button>
                                {publication.capabilities?.edit ? (
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        asChild
                                    >
                                        <Link
                                            href={routes.publicationEdit(
                                                publication.id
                                            )}
                                            aria-label={t(
                                                'common.edit',
                                                'Edit'
                                            )}
                                            title={t('common.edit', 'Edit')}
                                            data-testid="admin-publication-edit"
                                        >
                                            <PencilIcon />
                                        </Link>
                                    </Button>
                                ) : null}
                                {publication.capabilities?.delete ? (
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        className="text-destructive hover:text-destructive"
                                        aria-label={t(
                                            'common.delete',
                                            'Delete'
                                        )}
                                        title={t('common.delete', 'Delete')}
                                        onClick={() => remove(publication)}
                                    >
                                        <Trash2Icon />
                                    </Button>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            {list.hasNextPage ? (
                <div className="flex justify-center">
                    <Button
                        variant="outline"
                        data-testid="load-more"
                        loading={list.isFetchingNextPage}
                        onClick={() => list.fetchNextPage()}
                    >
                        {t('common.load_more', 'Load more')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
