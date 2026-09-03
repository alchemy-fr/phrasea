import {Workspace} from '../../../types';
import {putWorkspace} from '../../../api/collection';
import {
    deleteWorkspaceLogo,
    deleteWorkspaceTermsPdf,
    getWorkspace,
    uploadWorkspaceLogo,
    uploadWorkspaceTermsPdf,
} from '../../../api/workspace';
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
        defaultValues: {
            ...data,
            termsText: data.terms?.text ?? '',
            attachTermsToExports: data.terms?.attachToExports ?? false,
        },
        onSubmit: async data => {
            const {
                termsText,
                termsPdf,
                logoUpload,
                terms: _terms,
                logo: _logo,
                ...rest
            } = data;

            let workspace = await putWorkspace(data.id, {
                ...rest,
                terms: termsText,
            } as unknown as Partial<Workspace>);

            let refresh = false;
            if (termsPdf instanceof File) {
                workspace = await uploadWorkspaceTermsPdf(data.id, termsPdf);
            } else if (termsPdf === '') {
                await deleteWorkspaceTermsPdf(data.id);
                refresh = true;
            }

            if (logoUpload instanceof File) {
                workspace = await uploadWorkspaceLogo(data.id, logoUpload);
            } else if (logoUpload === '') {
                await deleteWorkspaceLogo(data.id);
                refresh = true;
            }

            if (refresh) {
                workspace = await getWorkspace(data.id);
            }

            return workspace;
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
