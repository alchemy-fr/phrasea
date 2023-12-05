import {Workspace} from '../../../types';
import {putWorkspace} from '../../../api/collection';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import {WorkspaceForm, WorkspaceFormData} from '../../Form/WorkspaceForm';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {
    extendSortableList,
    flattenSortableList,
} from '../../Form/SortableCollectionWidget.tsx';

type Props = {
    id: string;
    data: Workspace;
} & DialogTabProps;

export default function EditWorkspace({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const usedFormSubmit = useFormSubmit<WorkspaceFormData, Workspace>({
        defaultValues: normalizeFormData(data),
        onSubmit: async (data: WorkspaceFormData) => {
            return await putWorkspace(data.id, denormalizeFormData(data));
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

function denormalizeFormData(data: WorkspaceFormData): Workspace {
    return {
        ...data,
        enabledLocales: flattenSortableList(data.enabledLocales),
        localeFallbacks: flattenSortableList(data.localeFallbacks),
    };
}

function normalizeFormData(data: Workspace): WorkspaceFormData {
    return {
        ...data,
        enabledLocales: extendSortableList(data.enabledLocales),
        localeFallbacks: extendSortableList(data.localeFallbacks),
    };
}
