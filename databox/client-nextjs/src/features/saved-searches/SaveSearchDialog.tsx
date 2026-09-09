'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {SavedSearch, SavedSearchPrivacy} from '@/types/api';
import {postSavedSearch, putSavedSearch} from '@/lib/api/misc';
import type {SearchContextValue} from '@/features/search/SearchProvider';
import {SavedSearchPrivacyField} from './SavedSearchPrivacyField';

type Props = ModalProps & {
    /** Caller's search context (modals render above SearchProvider) */
    search: SearchContextValue;
    mode: 'create' | 'update';
    savedSearch?: SavedSearch;
};

export function SaveSearchDialog({
    open,
    onOpenChange,
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
    const [loading, setLoading] = useState(false);

    const submit = async () => {
        setLoading(true);
        try {
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
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'update'
                            ? t('saved_search.update', 'Update search')
                            : t('saved_search.save', 'Save search')}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody>
                    <FormRow label={t('common.name', 'Name')} htmlFor="ss-name">
                        <Input
                            id="ss-name"
                            autoFocus
                            value={name}
                            onChange={e => setName(e.target.value)}
                        />
                    </FormRow>
                    <SavedSearchPrivacyField
                        value={privacy}
                        onChange={setPrivacy}
                    />
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        loading={loading}
                        disabled={!name.trim()}
                    >
                        {t('common.save', 'Save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
