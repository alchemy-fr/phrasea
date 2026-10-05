'use client';

import {useCallback} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import {type AQLQuery, SavedSearchPrivacy} from '@/types/api';
import {postSavedSearch} from '@/lib/api/misc';
import {toastError} from '@/lib/utils/errors';

/**
 * Pins a filter of the sidebar (a workspace, a collection…) as a saved
 * search holding that single condition, named after it and kept secret.
 */
export function usePinAsSavedSearch() {
    const {t} = useTranslation();
    const queryClient = useQueryClient();

    return useCallback(
        async (name: string, condition: AQLQuery): Promise<void> => {
            try {
                const result = await postSavedSearch({
                    name,
                    privacy: SavedSearchPrivacy.Secret,
                    data: {query: '', conditions: [condition], sortBy: []},
                });
                queryClient.setQueryData(['saved-search', result.id], result);
                void queryClient.invalidateQueries({
                    queryKey: ['saved-searches'],
                });
                toast.success(
                    t(
                        'saved_search.pinned',
                        '"{{name}}" pinned to the saved searches',
                        {name}
                    )
                );
            } catch (e) {
                toastError(e);
            }
        },
        [t, queryClient]
    );
}
