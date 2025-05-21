import {AttributeList} from '../../../types';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {useFormPrompt} from '@alchemy/navigation';
import {AttributeListForm} from '../../Form/AttributeListForm';
import {putAttributeList} from "../../../api/attributeList.ts";
import {useAttributeListStore} from "../../../store/attributeListStore.ts";

type Props = {
    id: string;
    data: AttributeList;
} & DialogTabProps;

export default function EditAttributeList({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const updateAttributeList = useAttributeListStore(state => state.updateAttributeList);

    const usedFormSubmit = useFormSubmit({
        defaultValues: data,
        onSubmit: async (data: AttributeList) => {
            return await putAttributeList(data.id, data);
        },
        onSuccess: data => {
            updateAttributeList(data);

            toast.success(
                t('form.attribute_list_edit.success', 'Attribute List edited!') as string
            );
            onClose();
        },
    });

    const {submitting, remoteErrors, forbidNavigation} = usedFormSubmit;
    useFormPrompt(t, forbidNavigation);

    const formId = 'edit-attr-list';

    return (
        <FormTab
            onClose={onClose}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            minHeight={minHeight}
        >
            <AttributeListForm
                usedFormSubmit={usedFormSubmit}
                data={data}
                formId={formId}
            />
        </FormTab>
    );
}
