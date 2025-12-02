import {Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {StackedModalProps, useFormPrompt, useModals} from '@alchemy/navigation';
import React from 'react';
import {AppDialog} from '@alchemy/phrasea-ui';
import {TSearchContext} from '../SearchContext.tsx';
import {SavedSearch} from '../../../../types.ts';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import {getSearchData, postSavedSearch} from '../../../../api/savedSearch.ts';
import {LoadingButton} from '@mui/lab';
import SavedSearchFields from './SavedSearchFields.tsx';
import {useSavedSearchStore} from '../../../../store/savedSearchStore.ts';

type Props = {
    search: TSearchContext;
    onCreate: (entity: SavedSearch) => void;
} & StackedModalProps;

export default function SaveSearchDialog({
    search,
    onCreate,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const addItem = useSavedSearchStore(state => state.add);

    const usedFormSubmit = useFormSubmit<SavedSearch>({
        defaultValues: {
            title: search.query || '',
            public: false,
        },
        onSubmit: async data => {
            const d = {
                ...data,
                data: getSearchData(search),
            };

            const item = await postSavedSearch(d);
            addItem(item);

            return item;
        },
        onSuccess: data => {
            onCreate(data);

            toast.success(
                t('saved_search.form.created', 'Search was saved!') as string
            );
            closeModal();
        },
    });
    const {submitting, forbidNavigation, handleSubmit} = usedFormSubmit;

    useFormPrompt(t, forbidNavigation, modalIndex);
    const formId = 'save-search-form';

    return (
        <AppDialog
            maxWidth={'md'}
            onClose={closeModal}
            title={t('save_search.dialog.title', 'Save Search')}
            open={open}
            modalIndex={modalIndex}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <LoadingButton
                        loading={submitting}
                        disabled={submitting}
                        variant={'contained'}
                        form={formId}
                        type={'submit'}
                        color={'primary'}
                    >
                        {t('common.save', 'Save')}
                    </LoadingButton>
                </>
            )}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <SavedSearchFields usedFormSubmit={usedFormSubmit} />
            </form>
        </AppDialog>
    );
}
