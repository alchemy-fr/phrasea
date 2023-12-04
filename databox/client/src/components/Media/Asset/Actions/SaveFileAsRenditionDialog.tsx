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

export default function SaveFileAsRenditionDialog({asset, file, open, modalIndex}: Props) {
    const {closeModal} = useModals();

    const {
        control,
        formState: {errors},
        handleSubmit,
        remoteErrors,
        submitting,
        forbidNavigation
    } = useFormSubmit({
        defaultValues: {
            definition: undefined,
        },
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
    useDirtyFormPrompt(forbidNavigation);

    const formId = 'save-file-as-rendition';

    return (
        <FormDialog
            title={`Save file as asset rendition`}
            open={open}
            modalIndex={modalIndex}
            loading={submitting}
            formId={formId}
            submitIcon={<FileCopyIcon />}
            submitLabel={'Save'}
        >
            <form id={formId} onSubmit={handleSubmit}>
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
