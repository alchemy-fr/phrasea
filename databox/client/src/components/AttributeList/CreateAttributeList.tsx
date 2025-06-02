import FormDialog from '../Dialog/FormDialog';
import {AttributeListForm} from '../Form/AttributeListForm';
import {AttributeList} from '../../types';
import {useFormSubmit} from '@alchemy/api';
import {postAttributeList} from '../../api/attributeList';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useDirtyFormPrompt} from '../Dialog/Tabbed/FormTab';
import {useAttributeListStore} from '../../store/attributeListStore';

type Props = {
    onCreate?: (data: AttributeList) => void;
} & StackedModalProps;

export default function CreateAttributeList({
    modalIndex,
    open,
    onCreate,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const addAttributeList = useAttributeListStore(
        state => state.addAttributeList
    );

    const usedFormSubmit = useFormSubmit<AttributeList>({
        defaultValues: {
            title: '',
        },
        onSubmit: async (data: AttributeList) => {
            return await postAttributeList(data);
        },
        onSuccess: data => {
            toast.success(
                t(
                    'form.attributelist_create.success',
                    'Attribute List created!'
                ) as string
            );
            addAttributeList(data);
            closeModal();

            onCreate && onCreate(data);
        },
    });

    const {submitting, remoteErrors, forbidNavigation} = usedFormSubmit;
    useDirtyFormPrompt(forbidNavigation, modalIndex);
    const formId = 'create-attr-list';

    return (
        <FormDialog
            modalIndex={modalIndex}
            title={t(
                'form.attributelist_create.title',
                'Create Attribute List'
            )}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            open={open}
        >
            <AttributeListForm
                formId={formId}
                usedFormSubmit={usedFormSubmit}
            />
        </FormDialog>
    );
}
