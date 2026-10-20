'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {
    useInfiniteQuery,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {
    BookmarkIcon,
    FlaskConicalIcon,
    MoreVerticalIcon,
    PencilIcon,
    PlayIcon,
    SearchIcon,
    Trash2Icon,
    ZapIcon,
} from 'lucide-react';
import type {SavedSearch, SavedSearchAutomation} from '@/types/api';
import {deleteSavedSearch, getSavedSearches} from '@/lib/api/misc';
import {getSavedSearchAutomations} from '@/lib/api/automations';
import {useOptionalSearch} from '@/features/search/SearchProvider';
import {FilterInput} from '@/components/ui/filter-input';
import {Button} from '@/components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import {PanelSection} from '@/components/layout/PanelSection';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';
import {automationsQueryKey} from './automations/queryKeys';
import {useRunAutomation} from './automations/useRunAutomation';
import {AutomationFormDialog} from './automations/AutomationForm';

/**
 * The same menu is rendered as a context menu and as a dropdown (⋮): the
 * primitives of either family, under one shape.
 */
type MenuParts = {
    Item: typeof DropdownMenuItem;
    Sep: typeof DropdownMenuSeparator;
    Sub: typeof DropdownMenuSub;
    SubTrigger: typeof DropdownMenuSubTrigger;
    SubContent: typeof DropdownMenuSubContent;
};

const dropdownParts: MenuParts = {
    Item: DropdownMenuItem,
    Sep: DropdownMenuSeparator,
    Sub: DropdownMenuSub,
    SubTrigger: DropdownMenuSubTrigger,
    SubContent: DropdownMenuSubContent,
};

const contextParts = {
    Item: ContextMenuItem,
    Sep: ContextMenuSeparator,
    Sub: ContextMenuSub,
    SubTrigger: ContextMenuSubTrigger,
    SubContent: ContextMenuSubContent,
} as unknown as MenuParts;

export function SavedSearchList() {
    const {t} = useTranslation();
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
                <FilterInput value={filter} onValueChange={setFilter} />
            </div>
            {items.length === 0 && !list.isLoading ? (
                <p className="px-3 pb-3 text-xs text-muted-foreground">
                    {t('saved_search.list.empty', 'No saved search')}
                </p>
            ) : null}
            <ul>
                {items.map(s => (
                    <SavedSearchRow key={s.id} savedSearch={s} />
                ))}
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

function SavedSearchRow({savedSearch: s}: {savedSearch: SavedSearch}) {
    const {t} = useTranslation();
    const router = useRouter();
    const search = useOptionalSearch();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const active = search?.searchId === s.id;
    const manageable =
        s.capabilities.edit ||
        s.capabilities.delete ||
        s.capabilities.manageAutomations;

    // Automations are fetched when a menu opens, not for every listed search
    const [menuOpen, setMenuOpen] = useState(false);
    const automations = useQuery({
        queryKey: automationsQueryKey(s.id),
        queryFn: () => getSavedSearchAutomations(s.id),
        enabled: menuOpen && s.capabilities.manageAutomations,
    });

    const open = () => {
        if (search) {
            search.loadSavedSearch(s);
        } else {
            router.push(`${routes.assets()}?id=${s.id}`);
        }
    };

    const menu = (
        {Item, Sep, Sub, SubTrigger, SubContent}: MenuParts,
        withOpen: boolean
    ) => {
        const hasActions = withOpen || s.capabilities.edit;

        return (
            <>
                {withOpen ? (
                    <Item onSelect={open}>
                        <SearchIcon /> {t('common.open', 'Open')}
                    </Item>
                ) : null}
                {s.capabilities.edit ? (
                    <Item
                        onSelect={() =>
                            router.push(routes.savedSearchManage(s.id, 'edit'))
                        }
                    >
                        <PencilIcon /> {t('common.edit', 'Edit')}
                    </Item>
                ) : null}
                {s.capabilities.delete ? (
                    <>
                        {hasActions ? <Sep /> : null}
                        <Item
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
                                        await deleteSavedSearch(s.id);
                                        void queryClient.invalidateQueries({
                                            queryKey: ['saved-searches'],
                                        });
                                        if (active) {
                                            search?.setSearchId(undefined);
                                        }
                                    },
                                })
                            }
                        >
                            <Trash2Icon /> {t('common.delete', 'Delete')}
                        </Item>
                    </>
                ) : null}
                {s.capabilities.manageAutomations ? (
                    <AutomationsMenu
                        savedSearchId={s.id}
                        automations={automations.data}
                        loading={automations.isLoading}
                        separator={hasActions || s.capabilities.delete}
                        parts={{Item, Sep, Sub, SubTrigger, SubContent}}
                    />
                ) : null}
            </>
        );
    };

    return (
        <ContextMenu onOpenChange={setMenuOpen}>
            <ContextMenuTrigger asChild>
                <li
                    data-testid="saved-search-item"
                    data-active={active ? 'true' : undefined}
                    className={cn(
                        'group flex items-center gap-1 px-2',
                        active && 'bg-primary/10',
                        // A menu open (context or ⋮): the search it acts on
                        'data-[state=open]:bg-accent has-[>[data-state=open]]:bg-accent'
                    )}
                >
                    <button
                        type="button"
                        className="flex min-w-0 flex-1 items-center gap-2 rounded px-1 py-1.5 text-left text-sm hover:bg-accent"
                        onClick={open}
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
                    {manageable ? (
                        <DropdownMenu onOpenChange={setMenuOpen}>
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
                                {menu(dropdownParts, false)}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null}
                </li>
            </ContextMenuTrigger>
            <ContextMenuContent>{menu(contextParts, true)}</ContextMenuContent>
        </ContextMenu>
    );
}

/**
 * One sub-menu per automation of the search: run it, or edit its
 * configuration. Nothing when the search has none.
 */
function AutomationsMenu({
    savedSearchId,
    automations,
    loading,
    separator,
    parts: {Item, Sep, Sub, SubTrigger, SubContent},
}: {
    savedSearchId: string;
    automations: SavedSearchAutomation[] | undefined;
    loading: boolean;
    separator: boolean;
    parts: MenuParts;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const runAutomation = useRunAutomation();

    if (loading) {
        return (
            <>
                {separator ? <Sep /> : null}
                <Item disabled>
                    <ZapIcon /> {t('common.loading', 'Loading…')}
                </Item>
            </>
        );
    }

    if (!automations?.length) {
        return null;
    }

    const edit = async (automation: SavedSearchAutomation) => {
        if (
            await openModal(AutomationFormDialog, {savedSearchId, automation})
        ) {
            void queryClient.invalidateQueries({
                queryKey: automationsQueryKey(savedSearchId),
            });
        }
    };

    return (
        <>
            {separator ? <Sep /> : null}
            {automations.map(a => (
                <Sub key={a.id}>
                    <SubTrigger
                        data-testid="saved-search-automation"
                        className={cn(!a.enabled && 'text-muted-foreground')}
                    >
                        <ZapIcon /> <span className="truncate">{a.name}</span>
                    </SubTrigger>
                    <SubContent>
                        {a.capabilities.run ? (
                            <>
                                <Item onSelect={() => runAutomation(a, false)}>
                                    <PlayIcon />{' '}
                                    {t('automation.run.now', 'Run now')}
                                </Item>
                                <Item onSelect={() => runAutomation(a, true)}>
                                    <FlaskConicalIcon />{' '}
                                    {t('automation.run.dry_run', 'Dry run')}
                                </Item>
                            </>
                        ) : null}
                        {a.capabilities.edit ? (
                            <>
                                {a.capabilities.run ? <Sep /> : null}
                                <Item onSelect={() => edit(a)}>
                                    <PencilIcon /> {t('common.edit', 'Edit')}
                                </Item>
                            </>
                        ) : null}
                    </SubContent>
                </Sub>
            ))}
        </>
    );
}
