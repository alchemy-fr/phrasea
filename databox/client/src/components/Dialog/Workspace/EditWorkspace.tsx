import {Workspace} from '../../../types';
import {putWorkspace} from '../../../api/collection';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import {WorkspaceForm} from '../../Form/WorkspaceForm';
import FormTab from '../Tabbed/FormTab';
import {DataTabProps} from '../Tabbed/TabbedDialog';

type Props = DataTabProps<Workspace>;

export default function EditWorkspace({
    data,
    setData,
    onClose,
    minHeight,
}: Props) {
    const {t} = useTranslation();

    const usedFormSubmit = useFormSubmit<Workspace>({
        defaultValues: data,
        onSubmit: async data => {
            return await putWorkspace(data.id, data);
        },
        onSuccess: data => {
            toast.success(
                t('form.workspace_edit.success', 'Workspace edited!') as string
            );
            setData?.(data);
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
            <WorkspaceForm
                usedFormSubmit={usedFormSubmit}
                formId={formId}
                data={data}
                setData={setData}
            />
        </FormTab>
    );
}
