import React from 'react';
import {ConfirmDialog} from '@alchemy/phrasea-framework';
import {StackedModalProps} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import {Alert, Radio, RadioGroup, Typography} from '@mui/material';
import CallMergeIcon from '@mui/icons-material/CallMerge';
import {Asset} from '../../../../types.ts';
import {
    bypassQuarantine,
    deleteAssets,
    DuplicateAsset,
    getAssetDuplicates,
} from '../../../../api/asset.ts';
import {useModalFetch} from '../../../../hooks/useModalFetch.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {useAssetStore} from '../../../../store/assetStore.ts';
import {toast} from 'react-toastify';
import DuplicateAssetRow, {
    duplicateToThumbAsset,
} from './DuplicateAssetRow.tsx';

type Props = {
    asset: Asset;
    onResolved?: () => void;
} & StackedModalProps;

type Choice = {
    id: string;
    title: string;
    subtitle?: string;
    thumbAsset: Asset;
    incoming?: boolean;
};

export default function MergeDuplicatesDialog({
    asset,
    onResolved,
    ...modalProps
}: Props) {
    const {t} = useTranslation();
    const storeUpdate = useAssetStore(s => s.update);
    const storeDelete = useAssetStore(s => s.delete);

    const {data: duplicates, isSuccess} = useModalFetch<DuplicateAsset[]>({
        queryKey: ['assets', asset.id, 'duplicates'],
        queryFn: () => getAssetDuplicates(asset.id),
        staleTime: 2000,
    });

    const incomingChoice: Choice = {
        id: asset.id,
        title:
            asset.source?.fileName ??
            asset.name ??
            t('quarantine.merge.incoming', 'Incoming file'),
        subtitle: t('quarantine.merge.incoming_hint', 'Newly uploaded file'),
        thumbAsset: asset,
        incoming: true,
    };

    const [keptId, setKeptId] = React.useState<string>(asset.id);

    React.useEffect(() => {
        // Default to keeping the first existing duplicate once loaded
        if (isSuccess && duplicates && duplicates.length > 0) {
            setKeptId(duplicates[0].id);
        }
    }, [isSuccess, duplicates]);

    if (!isSuccess) {
        return <FullPageLoader />;
    }

    const choices: Choice[] = [
        incomingChoice,
        ...duplicates.map(d => ({
            id: d.id,
            title: d.title ?? d.id,
            subtitle: d.createdAt
                ? t('quarantine.merge.existing_hint', {
                      defaultValue: 'Existing asset · {{date}}',
                      date: new Date(d.createdAt).toLocaleString(),
                  })
                : t('quarantine.merge.existing', 'Existing asset'),
            thumbAsset: duplicateToThumbAsset(d),
        })),
    ];

    const onConfirm = async () => {
        try {
            if (keptId === asset.id) {
                // Keep the incoming (quarantined) asset: accept it and trash
                // the existing duplicates it replaces.
                const updated = await bypassQuarantine(asset.id);
                const duplicateIds = duplicates.map(d => d.id);
                if (duplicateIds.length > 0) {
                    await deleteAssets(duplicateIds);
                    duplicateIds.forEach(storeDelete);
                }
                storeUpdate(updated);
            } else {
                // Keep an existing duplicate: trash the incoming one.
                await deleteAssets([asset.id]);
                storeDelete(asset.id);
            }

            toast.success(
                t('quarantine.merge.success', 'Duplicates merged') as string
            );
            onResolved?.();
        } catch (e) {
            toast.error(
                t(
                    'quarantine.merge.error',
                    'Failed to merge duplicates'
                ) as string
            );
            throw e;
        }
    };

    return (
        <ConfirmDialog
            {...modalProps}
            title={t('quarantine.merge.title', 'Resolve duplicates')}
            onConfirm={onConfirm}
            confirmButtonProps={{
                startIcon: <CallMergeIcon />,
            }}
        >
            {duplicates.length === 0 ? (
                <Alert severity={'info'}>
                    {t(
                        'quarantine.merge.no_duplicates',
                        'The duplicate assets are no longer available. You can keep this file by accepting it.'
                    )}
                </Alert>
            ) : (
                <>
                    <Typography sx={{mb: 2}}>
                        {t(
                            'quarantine.merge.description',
                            'This file is identical to existing asset(s). Choose which one to keep — the others will be moved to trash.'
                        )}
                    </Typography>
                    <RadioGroup
                        value={keptId}
                        onChange={e => setKeptId(e.target.value)}
                    >
                        {choices.map(choice => (
                            <DuplicateAssetRow
                                key={choice.id}
                                asset={choice.thumbAsset}
                                title={
                                    choice.incoming
                                        ? `${choice.title} (${t('quarantine.merge.new_badge', 'new')})`
                                        : choice.title
                                }
                                subtitle={choice.subtitle}
                                selected={keptId === choice.id}
                                onClick={() => setKeptId(choice.id)}
                                leading={
                                    <Radio
                                        value={choice.id}
                                        checked={keptId === choice.id}
                                    />
                                }
                            />
                        ))}
                    </RadioGroup>
                </>
            )}
        </ConfirmDialog>
    );
}
