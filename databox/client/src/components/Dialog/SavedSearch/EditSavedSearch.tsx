import {SavedSearch} from '../../../types';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {useFormPrompt} from '@alchemy/navigation';
import {useSavedSearchStore} from '../../../store/savedSearchStore.ts';
import {putSavedSearch} from '../../../api/savedSearch.ts';
import SavedSearchFields from '../../Media/Search/SavedSearch/SavedSearchFields.tsx';

type Props = {
    id: string;
    data: SavedSearch;
} & DialogTabProps;

export default function EditSavedSearch({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const {updateItem} = useSavedSearchStore();

    const usedFormSubmit = useFormSubmit({
        defaultValues: data,
        onSubmit: async (data: SavedSearch) => {
            return await putSavedSearch(data.id, data);
        },
        onSuccess: data => {
            updateItem(data);

            toast.success(
                t(
                    'form.saved_search_edit.success',
                    'Saved Search edited!'
                ) as string
            );
            onClose();
        },
    });

    const {submitting, remoteErrors, forbidNavigation, handleSubmit} =
        usedFormSubmit;
    useFormPrompt(t, forbidNavigation);

    const formId = 'edit-saved-search';

    return (
        <FormTab
            onClose={onClose}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            minHeight={minHeight}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <SavedSearchFields usedFormSubmit={usedFormSubmit} />
            </form>
        </FormTab>
    );
}
