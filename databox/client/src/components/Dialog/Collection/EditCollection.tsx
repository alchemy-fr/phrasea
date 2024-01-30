import {Collection} from '../../../types';
import {putCollection} from '../../../api/collection';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {CollectionForm} from '../../Form/CollectionForm';
import {useInRouterDirtyFormPrompt} from '@alchemy/navigation';
import {useCollectionStore} from "../../../store/collectionStore.ts";

export type OnCollectionEdit = (coll: Collection) => void;

type Props = {
    id: string;
    data: Collection;
} & DialogTabProps;

export default function EditCollection({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const {
        updateCollection
    } = useCollectionStore();

    const usedFormSubmit = useFormSubmit({
        defaultValues: data,
        onSubmit: async (data: Collection) => {
            return await putCollection(data.id, data);
        },
        onSuccess: (data) => {
            updateCollection(data);

            toast.success(
                t(
                    'form.collection_edit.success',
                    'Collection edited!'
                ) as string
            );
            onClose();
        },
    });

    const {submitting, remoteErrors, forbidNavigation} = usedFormSubmit;
    useInRouterDirtyFormPrompt(t, forbidNavigation);

    const formId = 'edit-collection';

    return (
        <FormTab
            onClose={onClose}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            minHeight={minHeight}
        >
            <CollectionForm
                usedFormSubmit={usedFormSubmit}
                data={data}
                formId={formId}
            />
        </FormTab>
    );
}
