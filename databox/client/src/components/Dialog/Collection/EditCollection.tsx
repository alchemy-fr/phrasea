import {Collection} from '../../../types';
import {putCollection} from '../../../api/collection';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab from '../Tabbed/FormTab';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import {CollectionForm} from '../../Form/CollectionForm';
import {useFormPrompt} from '@alchemy/navigation';
import {useCollectionStore} from '../../../store/collectionStore';

export type OnCollectionEdit = (coll: Collection) => void;

type Props = DataTabProps<Collection>;

export default function EditCollection({
    data,
    setData: __setData,
    onClose,
    minHeight,
}: Props) {
    const {t} = useTranslation();
    const {updateCollection} = useCollectionStore();

    const setData = (data: Collection) => {
        updateCollection(data);
        __setData?.(data);
    };

    const usedFormSubmit = useFormSubmit({
        defaultValues: data,
        onSubmit: async (data: Collection) => {
            return await putCollection(data.id, data);
        },
        onSuccess: data => {
            toast.success(
                t(
                    'form.collection_edit.success',
                    'Collection edited!'
                ) as string
            );
            onClose();
            setData(data);
        },
    });

    const {submitting, remoteErrors, forbidNavigation} = usedFormSubmit;
    useFormPrompt(t, forbidNavigation);

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
                setData={setData}
                formId={formId}
            />
        </FormTab>
    );
}
