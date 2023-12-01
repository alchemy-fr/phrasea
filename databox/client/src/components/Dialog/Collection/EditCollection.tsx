import {Collection} from '../../../types';
import {putCollection} from '../../../api/collection';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import useFormSubmit from '../../../hooks/useFormSubmit';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {CollectionForm} from '../../Form/CollectionForm';

export type OnCollectionEdit = (coll: Collection) => void;

type Props = {
    id: string;
    data: Collection;
} & DialogTabProps;

export default function EditCollection({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const {submitting, submitted, handleSubmit, errors} = useFormSubmit({
        onSubmit: async (data: Collection) => {
            return await putCollection(data.id, data);
        },
        onSuccess: () => {
            toast.success(
                t(
                    'form.collection_edit.success',
                    'Collection edited!'
                ) as string
            );
            onClose();
        },
    });

    const formId = 'edit-collection';

    return (
        <FormTab
            onClose={onClose}
            formId={formId}
            loading={submitting}
            errors={errors}
            minHeight={minHeight}
        >
            <CollectionForm
                data={data}
                formId={formId}
                onSubmit={handleSubmit}
                submitting={submitting}
                submitted={submitted}
            />
        </FormTab>
    );
}
