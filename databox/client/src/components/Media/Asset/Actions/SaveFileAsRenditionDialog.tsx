import {useForm} from 'react-hook-form';
import {FormGroup, FormLabel} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import RemoteErrors from '../../../Form/RemoteErrors';
import {Asset, File} from '../../../../types';
import {useDirtyFormPrompt} from '../../../Dialog/Tabbed/FormTab';
import {toast} from 'react-toastify';
import FormFieldErrors from '../../../Form/FormFieldErrors';
import FormRow from '../../../Form/FormRow';
import RenditionDefinitionSelect from '../../../Form/RenditionDefinitionSelect';
import {postRendition} from '../../../../api/rendition';
import {useFormSubmit} from '@alchemy/api';
import {useModals, StackedModalProps} from '@alchemy/navigation';

type FormData = {
    definition: string | undefined;
};

type Props = {
    asset: Asset;
    file: File;
} & StackedModalProps;

export default function SaveFileAsRenditionDialog({asset, file, open}: Props) {
    const {closeModal} = useModals();

    const {
        handleSubmit,
        setError,
        control,
        formState: {errors, isDirty},
    } = useForm<FormData>({
        defaultValues: {
            definition: undefined,
        },
    });

    const {
        handleSubmit: onSubmit,
        errors: remoteErrors,
        submitting,
        submitted,
    } = useFormSubmit({
        onSubmit: async (data: FormData) => {
            return await postRendition({
                definitionId: data.definition,
                assetId: asset.id,
                sourceFileId: file.id,
            });
        },
        onSuccess: () => {
            toast.success(`Rendition has been saved`);
            closeModal();
        },
    });
    useDirtyFormPrompt(!submitted && isDirty);

    const formId = 'save-file-as-rendition';

    return (
        <FormDialog
            title={`Save file as asset rendition`}
            open={open}
            loading={submitting}
            formId={formId}
            submitIcon={<FileCopyIcon />}
            submitLabel={'Save'}
        >
            <form id={formId} onSubmit={handleSubmit(onSubmit(setError))}>
                <FormRow>
                    <FormGroup>
                        <FormLabel>Rendition to add or replace</FormLabel>
                        <RenditionDefinitionSelect
                            disabled={submitting}
                            name={'definition'}
                            control={control}
                            workspaceId={asset.workspace.id}
                        />
                        <FormFieldErrors field={'definition'} errors={errors} />
                    </FormGroup>
                </FormRow>
            </form>
            <RemoteErrors errors={remoteErrors} />
        </FormDialog>
    );
}
