'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {useInfiniteQuery, useQueryClient} from '@tanstack/react-query';
import {
    BookmarkIcon,
    MoreVerticalIcon,
    PencilIcon,
    Trash2Icon,
} from 'lucide-react';
import {deleteSavedSearch, getSavedSearches} from '@/lib/api/misc';
import {useOptionalSearch} from '@/features/search/SearchProvider';
import {Input} from '@/components/ui/input';
import {Button} from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import {PanelSection} from '@/components/layout/PanelSection';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';

export function SavedSearchList() {
    const {t} = useTranslation();
    const router = useRouter();
    const search = useOptionalSearch();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const [filter, setFilter] = useState('');
    const query = useDebouncedValue(filter, 250);

    const list = useInfiniteQuery({
        queryKey: ['saved-searches', query],
        queryFn: ({pageParam}) =>
            getSavedSearches({query: query || undefined, url: pageParam}),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const items = list.data?.pages.flatMap(p => p.items) ?? [];

    return (
        <PanelSection
            title={t('saved_search.list.title', 'Saved searches')}
            icon={<BookmarkIcon />}
            defaultOpen
        >
            <div className="px-2 pb-2">
                <Input
                    value={filter}
                    onChange={e => setFilter(e.target.value)}
                    placeholder={t('common.filter', 'Filter…')}
                    className="h-8"
                />
            </div>
            {items.length === 0 && !list.isLoading ? (
                <p className="px-3 pb-3 text-xs text-muted-foreground">
                    {t('saved_search.list.empty', 'No saved search')}
                </p>
            ) : null}
            <ul>
                {items.map(s => {
                    const active = search?.searchId === s.id;

                    return (
                        <li
                            key={s.id}
                            className={cn(
                                'group flex items-center gap-1 px-2',
                                active && 'bg-primary/10'
                            )}
                        >
                            <button
                                type="button"
                                className="flex min-w-0 flex-1 items-center gap-2 rounded px-1 py-1.5 text-left text-sm hover:bg-accent"
                                onClick={() => {
                                    if (search) {
                                        search.loadSavedSearch(s);
                                    } else {
                                        router.push(
                                            `${routes.assets()}?id=${s.id}`
                                        );
                                    }
                                }}
                            >
                                <BookmarkIcon
                                    className={cn(
                                        'size-4 shrink-0',
                                        active
                                            ? 'text-primary'
                                            : 'text-muted-foreground'
                                    )}
                                />
                                <span className="truncate">{s.name}</span>
                            </button>
                            {s.capabilities.edit || s.capabilities.delete ? (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon-xs"
                                            className="opacity-0 group-hover:opacity-100 data-[state=open]:opacity-100"
                                        >
                                            <MoreVerticalIcon />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        {s.capabilities.edit ? (
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    router.push(
                                                        routes.savedSearchManage(
                                                            s.id,
                                                            'edit'
                                                        )
                                                    )
                                                }
                                            >
                                                <PencilIcon />{' '}
                                                {t('common.edit', 'Edit')}
                                            </DropdownMenuItem>
                                        ) : null}
                                        {s.capabilities.delete ? (
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onSelect={() =>
                                                    openModal(ConfirmDialog, {
                                                        title: t(
                                                            'saved_search.delete.title',
                                                            'Delete search "{{name}}"?',
                                                            {name: s.name}
                                                        ),
                                                        destructive: true,
                                                        onConfirm: async () => {
                                                            await deleteSavedSearch(
                                                                s.id
                                                            );
                                                            void queryClient.invalidateQueries(
                                                                {
                                                                    queryKey: [
                                                                        'saved-searches',
                                                                    ],
                                                                }
                                                            );
                                                            if (active) {
                                                                search?.setSearchId(
                                                                    undefined
                                                                );
                                                            }
                                                        },
                                                    })
                                                }
                                            >
                                                <Trash2Icon />{' '}
                                                {t('common.delete', 'Delete')}
                                            </DropdownMenuItem>
                                        ) : null}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            ) : null}
                        </li>
                    );
                })}
            </ul>
            {list.hasNextPage ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="mx-2 mb-2 w-[calc(100%-1rem)]"
                    onClick={() => list.fetchNextPage()}
                    loading={list.isFetchingNextPage}
                >
                    {t('common.load_more', 'Load more')}
                </Button>
            ) : null}
        </PanelSection>
    );
}
