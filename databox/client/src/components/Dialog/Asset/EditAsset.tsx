import {Asset} from '../../../types';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {AssetApiInput, putAsset} from '../../../api/asset';
import {AssetForm} from '../../Form/AssetForm';

type Props = {
    id: string;
    data: Asset;
} & DialogTabProps;

export default function EditAsset({data: asset, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const {submitting, submitted, handleSubmit, errors} = useFormSubmit({
        onSubmit: async (data: AssetApiInput) => {
            return await putAsset(asset.id, data);
        },
        onSuccess: () => {
            toast.success(
                t('form.asset_edit.success', 'Asset edited!') as string
            );
            onClose();
        },
    });

    const formId = 'edit-asset';

    return (
        <FormTab
            onClose={onClose}
            formId={formId}
            loading={submitting}
            errors={errors}
            minHeight={minHeight}
        >
            <AssetForm
                data={asset}
                formId={formId}
                onSubmit={handleSubmit}
                submitting={submitting}
                submitted={submitted}
                workspaceId={asset.workspace.id}
            />
        </FormTab>
    );
}
