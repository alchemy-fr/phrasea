import React from 'react';
import ConfirmDialog from "../../../Ui/ConfirmDialog";
import {useTranslation} from "react-i18next";
import {deleteAssets} from "../../../../api/asset";
import {StackedModalProps} from "../../../../hooks/useModalStack";

type Props = {
    assetIds: string[];
    onDelete?: () => void;
} & StackedModalProps;

export default function DeleteAssetsConfirm({
                                                assetIds,
                                                onDelete,
                                                open,
                                            }: Props) {
    const {t} = useTranslation();
    const count = assetIds.length;

    const onDeleteAssets = async () => {
        await deleteAssets(assetIds);
        onDelete && onDelete();
    };

    return <ConfirmDialog
        title={t('asset.delete.confirm.title', 'Confirm delete')}
        onConfirm={onDeleteAssets}
        open={open}
    >
        {count === 1 && t('asset.delete.confirm.message_one', 'Are you sure you want to delete this asset?')}
        {count > 1 && t('asset.delete.confirm.message_many', 'Are you sure you want to delete {{count}} assets?', {
            count,
        })}
    </ConfirmDialog>
}
