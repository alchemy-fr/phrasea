import {Workspace} from '../../../types';
import {putWorkspace} from '../../../api/collection';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import {WorkspaceForm} from '../../Form/WorkspaceForm';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';

type Props = {
    id: string;
    data: Workspace;
} & DialogTabProps;

export default function EditWorkspace({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const usedFormSubmit = useFormSubmit<Workspace>({
        defaultValues: data,
        onSubmit: async data => {
            return await putWorkspace(data.id, data);
        },
        onSuccess: () => {
            toast.success(
                t('form.workspace_edit.success', 'Workspace edited!') as string
            );
            onClose();
        },
    });

    const {submitting, remoteErrors} = usedFormSubmit;

    const formId = 'edit-ws';

    return (
        <FormTab
            onClose={onClose}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            minHeight={minHeight}
        >
            <WorkspaceForm usedFormSubmit={usedFormSubmit} formId={formId} />
        </FormTab>
    );
}
