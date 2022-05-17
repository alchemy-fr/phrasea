import React from 'react';
import {StackedModalProps} from "@mattjennings/react-modal-stack";
import ConfirmDialog from "../../../Ui/ConfirmDialog";
import {useTranslation} from "react-i18next";
import {deleteAsset} from "../../../../api/asset";

type Props = {
    count: number;
    assetIds: string[];
    onDelete?: () => void;
} & StackedModalProps;

export default function DeleteAssetsConfirm({
                                                count,
                                                assetIds,
                                                onDelete,
                                            }: Props) {
    const {t} = useTranslation();

    const onDeleteAssets = async () => {
        await Promise.all(assetIds.map(deleteAsset));
        onDelete && onDelete();
    };

    return <ConfirmDialog
        title={t('asset.delete.confirm.title', 'Confirm delete')}
        onConfirm={onDeleteAssets}
    >
        {t('asset.delete.confirm.message', 'Are you sure you want to delete {{count}} assets?', {
            count,
        })}
    </ConfirmDialog>
}
