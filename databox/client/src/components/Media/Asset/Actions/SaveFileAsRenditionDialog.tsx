import {FormGroup, FormLabel} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import RemoteErrors from '../../../Form/RemoteErrors';
import {Asset, File} from '../../../../types';
import {toast} from 'react-toastify';
import {FormFieldErrors} from '@alchemy/react-form';
import {FormRow} from '@alchemy/react-form';
import RenditionDefinitionSelect from '../../../Form/RenditionDefinitionSelect';
import {useTranslation} from 'react-i18next';
import {postRendition} from '../../../../api/rendition';
import {useFormSubmit} from '@alchemy/api';
import {
    useModals,
    StackedModalProps,
    useOutsideRouterDirtyFormPrompt,
} from '@alchemy/navigation';

type FormData = {
    definition: string | undefined;
};

type Props = {
    asset: Asset;
    file: File;
} & StackedModalProps;

export default function SaveFileAsRenditionDialog({
    asset,
    file,
    open,
    modalIndex,
}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    const {
        control,
        formState: {errors},
        handleSubmit,
        remoteErrors,
        submitting,
        forbidNavigation,
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
    useOutsideRouterDirtyFormPrompt(t, forbidNavigation, modalIndex);

    const formId = 'save-file-as-rendition';

    return (
        <FormDialog
            title={`Save file as asset rendition`}
            open={open}
            modalIndex={modalIndex}
            loading={submitting}
            formId={formId}
            submitIcon={<FileCopyIcon />}
            submitLabel={t('common.save', `Save`)}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <FormGroup>
                        <FormLabel>{t('save_file_as_rendition_dialog.rendition_to_add_or_replace', `Rendition to add or replace`)}</FormLabel>
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
