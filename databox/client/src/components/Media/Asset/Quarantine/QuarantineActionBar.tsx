import React from 'react';
import {Button, Stack} from '@mui/material';
import {useTranslation} from 'react-i18next';
import DeleteIcon from '@mui/icons-material/Delete';
import DeleteForeverIcon from '@mui/icons-material/DeleteForever';
import ApprovalIcon from '@mui/icons-material/Approval';
import CallMergeIcon from '@mui/icons-material/CallMerge';
import LayersIcon from '@mui/icons-material/Layers';
import {useModals} from '@alchemy/navigation';
import {toast} from 'react-toastify';
import {Asset} from '../../../../types.ts';
import {bypassQuarantine} from '../../../../api/asset.ts';
import {useAssetStore} from '../../../../store/assetStore.ts';
import {
    AnalyzerName,
    FileAnalysis,
    hasAnalysisDuplicates,
    hasAnalyzerDuplicates,
} from './analysisTypes.ts';
import MergeDuplicatesDialog from './MergeDuplicatesDialog.tsx';
import AddAsVersionDialog from './AddAsVersionDialog.tsx';
import DeleteAssetsConfirmDialog from '../Actions/DeleteAssetsConfirmDialog.tsx';

type Props = {
    asset: Asset;
    analysis: FileAnalysis | null | undefined;
};

export default function QuarantineActionBar({asset, analysis}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const storeUpdate = useAssetStore(s => s.update);
    const [bypassing, setBypassing] = React.useState(false);

    const canBypass =
        (asset.capabilities as {bypassQuarantine?: boolean}).bypassQuarantine ??
        false;
    const hasDuplicates = hasAnalysisDuplicates(analysis);
    const canAddAsVersion = hasAnalyzerDuplicates(
        analysis,
        AnalyzerName.DocUniqueId
    );

    const onBypass = async () => {
        setBypassing(true);
        try {
            const updated = await bypassQuarantine(asset.id);
            storeUpdate(updated);
            toast.success(
                t(
                    'quarantine.actions.bypass_success',
                    'Asset accepted'
                ) as string
            );
        } catch (e) {
            toast.error(
                t(
                    'quarantine.actions.bypass_error',
                    'Failed to accept asset'
                ) as string
            );
            throw e;
        } finally {
            setBypassing(false);
        }
    };

    const onMerge = () => {
        openModal(MergeDuplicatesDialog, {asset});
    };

    const onAddAsVersion = () => {
        openModal(AddAsVersionDialog, {asset});
    };

    const onMoveToTrash = () => {
        openModal(DeleteAssetsConfirmDialog, {
            assetIds: [asset.id],
            onDelete: () =>
                storeUpdate({
                    ...asset,
                    deleted: true,
                }),
        });
    };

    const onDeletePermanently = () => {
        openModal(DeleteAssetsConfirmDialog, {
            assetIds: [asset.id],
            hardDelete: true,
            onDelete: () => {
                storeUpdate({
                    ...asset,
                    deleted: true,
                });
            },
        });
    };

    const onMouseDown = (e: React.MouseEvent<HTMLButtonElement>) =>
        e.stopPropagation();

    return (
        <Stack direction={'row'} gap={1} sx={{flexWrap: 'wrap'}}>
            {canBypass ? (
                <Button
                    variant={'contained'}
                    color={'error'}
                    startIcon={<ApprovalIcon />}
                    disabled={bypassing}
                    onClick={onBypass}
                    onMouseDown={onMouseDown}
                >
                    {t('quarantine.actions.bypass', 'By pass (accept)')}
                </Button>
            ) : null}

            {hasDuplicates ? (
                <Button
                    variant={'outlined'}
                    startIcon={<CallMergeIcon />}
                    onClick={onMerge}
                    onMouseDown={onMouseDown}
                >
                    {t('quarantine.actions.merge', 'Merge duplicates')}
                </Button>
            ) : null}

            {canAddAsVersion ? (
                <Button
                    variant={'outlined'}
                    startIcon={<LayersIcon />}
                    onClick={onAddAsVersion}
                    onMouseDown={onMouseDown}
                >
                    {t(
                        'quarantine.actions.add_as_version',
                        'Add as new version'
                    )}
                </Button>
            ) : null}

            <Button
                variant={'outlined'}
                startIcon={<DeleteIcon />}
                onClick={onMoveToTrash}
                onMouseDown={onMouseDown}
            >
                {t('quarantine.actions.move_to_trash', 'Move to trash')}
            </Button>

            <Button
                variant={'outlined'}
                color={'error'}
                startIcon={<DeleteForeverIcon />}
                onClick={onDeletePermanently}
                onMouseDown={onMouseDown}
            >
                {t('quarantine.actions.delete', 'Delete permanently')}
            </Button>
        </Stack>
    );
}
