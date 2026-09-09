'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    BookmarkIcon,
    BookmarkPlusIcon,
    EraserIcon,
    MoreVerticalIcon,
    SaveIcon,
    BugIcon,
} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useSearch} from './SearchProvider';
import {useResults} from './ResultProvider';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useModals} from '@/components/modals/ModalProvider';
import {SaveSearchDialog} from '@/features/saved-searches/SaveSearchDialog';
import {getSavedSearch} from '@/lib/api/misc';
import {searchChecksum} from './searchState';
import {DebugEsDialog} from './DebugEsDialog';

export function SearchMoreMenu() {
    const {t} = useTranslation();
    const search = useSearch();
    const results = useResults();
    const {isAuthenticated} = useAuth();
    const {openModal} = useModals();

    const saved = useQuery({
        queryKey: ['saved-search', search.searchId],
        queryFn: () => getSavedSearch(search.searchId!),
        enabled: !!search.searchId && isAuthenticated,
    });

    const savedChecksum = saved.data
        ? searchChecksum({
              query: saved.data.data.query ?? '',
              conditions: saved.data.data.conditions ?? [],
              sortBy: saved.data.data.sortBy ?? [],
          })
        : undefined;
    const currentChecksum = searchChecksum({
        query: search.query,
        conditions: search.conditions,
        sortBy: search.sortBy,
    });
    const isDirty =
        savedChecksum !== undefined && savedChecksum !== currentChecksum;
    const canEditSaved = !!saved.data?.capabilities.edit;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('search.more', 'More')}
                >
                    <MoreVerticalIcon />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuItem
                    disabled={!search.hasSearch && !search.searchId}
                    onSelect={search.reset}
                >
                    <EraserIcon /> {t('search.clear_search', 'Clear search')}
                </DropdownMenuItem>
                {isAuthenticated ? (
                    <>
                        <DropdownMenuSeparator />
                        {search.searchId && saved.data ? (
                            <>
                                <DropdownMenuItem
                                    disabled={!isDirty || !canEditSaved}
                                    onSelect={() =>
                                        openModal(SaveSearchDialog, {
                                            search,
                                            savedSearch: saved.data,
                                            mode: 'update',
                                        })
                                    }
                                >
                                    <SaveIcon />{' '}
                                    {t('saved_search.update', 'Update search')}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() =>
                                        openModal(SaveSearchDialog, {
                                            search,
                                            mode: 'create',
                                        })
                                    }
                                >
                                    <BookmarkPlusIcon />{' '}
                                    {t(
                                        'saved_search.save_as_new',
                                        'Save as new search'
                                    )}
                                </DropdownMenuItem>
                            </>
                        ) : (
                            <DropdownMenuItem
                                disabled={!search.hasSearch}
                                onSelect={() =>
                                    openModal(SaveSearchDialog, {
                                        search,
                                        mode: 'create',
                                    })
                                }
                            >
                                <BookmarkIcon />{' '}
                                {t('saved_search.save', 'Save search')}
                            </DropdownMenuItem>
                        )}
                    </>
                ) : null}
                {results.debug ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() =>
                                openModal(DebugEsDialog, {
                                    debug: results.debug!,
                                })
                            }
                        >
                            <BugIcon /> {t('search.debug', 'Search debug')}
                        </DropdownMenuItem>
                    </>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
