import React, {useEffect} from 'react';
import ConfirmDialog from "../../../Ui/ConfirmDialog";
import {useTranslation} from "react-i18next";
import {deleteAssets} from "../../../../api/asset";
import {StackedModalProps} from "../../../../hooks/useModalStack";
import {useModalHash} from "../../../../hooks/useModalHash";
import {Button} from "@mui/material";

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

    const {openModal} = useModalHash();

    const onDeleteAssets = async () => {
        await deleteAssets(assetIds);
        onDelete && onDelete();
    };

    return <ConfirmDialog
        title={t('asset.delete.confirm.title', 'Confirm delete')}
        onConfirm={onDeleteAssets}
        open={open}
    >
        <Button
            onClick={() => openModal(DeleteAssetsConfirm, {
                assetIds,
                onDelete,
            })}
        >SUB</Button>
        {count === 1 && t('asset.delete.confirm.message_one', 'Are you sure you want to delete this asset?')}
        {count > 1 && t('asset.delete.confirm.message_many', 'Are you sure you want to delete {{count}} assets?', {
            count,
        })}
    </ConfirmDialog>
}
