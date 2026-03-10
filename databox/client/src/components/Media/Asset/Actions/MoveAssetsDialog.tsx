import {useTranslation} from 'react-i18next';
import {Typography} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import {useFormSubmit} from '@alchemy/api';
import CollectionTreeWidget from '../../../Form/CollectionTreeWidget';
import {
    getWorkspaceOrCollectionIri,
    moveAssets,
} from '../../../../api/collection';
import {FormFieldErrors, RemoteErrors} from '@alchemy/react-form';
import DriveFileMoveIcon from '@mui/icons-material/DriveFileMove';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {TreeNode, useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {toast} from 'react-toastify';
import {WorkspaceOrCollectionTreeItem} from '../../Collection/CollectionTree/types.ts';

type Props = {
    assetIds: string[];
    workspaceId: string;
    onComplete: () => void;
} & StackedModalProps;

type FormData = {
    destination: string;
};

export default function MoveAssetsDialog({
    assetIds,
    workspaceId,
    onComplete,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const count = assetIds.length;

    const {
        control,
        formState: {errors},
        handleSubmit,
        remoteErrors,
        submitting,
        forbidNavigation,
    } = useFormSubmit({
        onSubmit: (data: FormData) => {
            console.log('data.destination', data.destination);
            return moveAssets(
                assetIds,
                getWorkspaceOrCollectionIri(
                    (
                        data.destination as unknown as TreeNode<WorkspaceOrCollectionTreeItem>
                    ).data
                )
            );
        },
        onSuccess: () => {
            toast.success(
                t('move_assets_dialog.assets_were_moved', `Assets were moved`)
            );
            closeModal();
            onComplete();
        },
    });
    useDirtyFormPrompt(forbidNavigation, modalIndex);

    const formId = 'move-assets';

    return (
        <FormDialog
            modalIndex={modalIndex}
            open={open}
            title={t('move_assets.dialog.title', 'Move {{count}} asset', {
                defaultValue_other: 'Move {{count}} assets',
                count,
            })}
            loading={submitting}
            formId={formId}
            submitIcon={<DriveFileMoveIcon />}
            submitLabel={t('move_assets.dialog.submit', 'Move')}
        >
            <Typography sx={{mb: 3}}>
                {t(
                    'move_assets.dialog.intro',
                    'Where do you want to move the selected assets?'
                )}
            </Typography>
            <form id={formId} onSubmit={handleSubmit}>
                <CollectionTreeWidget
                    isSelectable={node => node.data.capabilities.createAsset}
                    workspaceId={workspaceId}
                    control={control}
                    name={'destination'}
                    rules={{
                        required: true,
                    }}
                    label={t(
                        'form.move_assets.destination.label',
                        'Destination'
                    )}
                />
                <FormFieldErrors field={'destination'} errors={errors} />
            </form>
            <RemoteErrors errors={remoteErrors} />
        </FormDialog>
    );
}
