'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {FormRow, Input} from '@/components/ui/input';
import {SavedSearch, SavedSearchPrivacy} from '@/types/api';
import {postSavedSearch, putSavedSearch} from '@/lib/api/misc';
import type {SearchContextValue} from '@/features/search/SearchProvider';
import {SavedSearchPrivacyField} from './SavedSearchPrivacyField';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';

type Props = ModalProps<SavedSearch> & {
    /** Caller's search context (modals render above SearchProvider) */
    search: SearchContextValue;
    mode: 'create' | 'update';
    savedSearch?: SavedSearch;
};

export function SaveSearchDialog({
    open,
    onOpenChange,
    resolve,
    mode,
    savedSearch,
    search,
}: Props) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [name, setName] = useState(
        mode === 'update'
            ? (savedSearch?.name ?? '')
            : search.query || savedSearch?.name || ''
    );
    const [privacy, setPrivacy] = useState<SavedSearchPrivacy>(
        savedSearch?.privacy ?? SavedSearchPrivacy.Secret
    );

    const {dirty} = useDirtyState({name, privacy});

    const submit = async () => {
        const data = {
            name,
            privacy,
            data: {
                query: search.query,
                conditions: search.conditions,
                sortBy: search.sortBy,
            },
        };
        const result =
            mode === 'update' && savedSearch
                ? await putSavedSearch(savedSearch.id, data)
                : await postSavedSearch(data);
        void queryClient.invalidateQueries({queryKey: ['saved-searches']});
        queryClient.setQueryData(['saved-search', result.id], result);
        if (result.id !== search.searchId) {
            search.setSearchId(result.id);
        }
        toast.success(
            mode === 'update'
                ? t('saved_search.updated', 'Search updated')
                : t('saved_search.saved', 'Search saved')
        );
        resolve?.(result);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                mode === 'update'
                    ? t('saved_search.update', 'Update search')
                    : t('saved_search.save', 'Save search')
            }
            submitLabel={t('common.save', 'Save')}
            canSubmit={!!name.trim()}
            dirty={dirty}
            onSubmit={submit}
        >
            <FormRow label={t('common.name', 'Name')} htmlFor="ss-name">
                <Input
                    id="ss-name"
                    autoFocus
                    value={name}
                    onChange={e => setName(e.target.value)}
                />
            </FormRow>
            <SavedSearchPrivacyField value={privacy} onChange={setPrivacy} />
        </FormDialog>
    );
}
