import {AttributeList, Basket} from '../../../types';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {useFormPrompt} from '@alchemy/navigation';
import {useBasketStore} from '../../../store/basketStore';
import {AttributeListForm} from '../../Form/AttributeListForm';
import {putAttributeList} from "../../../api/attributeList.ts";

type Props = {
    id: string;
    data: AttributeList;
} & DialogTabProps;

export default function EditAttributeList({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const {updateBasket} = useBasketStore();

    const usedFormSubmit = useFormSubmit({
        defaultValues: data,
        onSubmit: async (data: AttributeList) => {
            return await putAttributeList(data.id, data);
        },
        onSuccess: data => {
            updateBasket(data);

            toast.success(
                t('form.basket_edit.success', 'Basket edited!') as string
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
