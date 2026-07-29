import React from 'react';
import {ConfirmDialog} from '@alchemy/phrasea-framework';
import {StackedModalProps} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import {
    Alert,
    Avatar,
    Box,
    Radio,
    RadioGroup,
    Stack,
    Typography,
} from '@mui/material';
import ImageIcon from '@mui/icons-material/Image';
import LayersIcon from '@mui/icons-material/Layers';
import {Asset} from '../../../../types.ts';
import {
    addAsAssetVersion,
    DuplicateAsset,
    getAssetDuplicates,
} from '../../../../api/asset.ts';
import {useModalFetch} from '../../../../hooks/useModalFetch.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {useAssetStore} from '../../../../store/assetStore.ts';
import {toast} from 'react-toastify';
import {AnalyzerName} from './analysisTypes.ts';

type Props = {
    asset: Asset;
    onResolved?: () => void;
} & StackedModalProps;

export default function AddAsVersionDialog({
    asset,
    onResolved,
    ...modalProps
}: Props) {
    const {t} = useTranslation();
    const storeUpdate = useAssetStore(s => s.update);
    const storeDelete = useAssetStore(s => s.delete);
    const [targetId, setTargetId] = React.useState<string | undefined>();

    const {data: duplicates, isSuccess} = useModalFetch<DuplicateAsset[]>({
        queryKey: ['assets', asset.id, 'duplicates'],
        queryFn: () => getAssetDuplicates(asset.id),
        staleTime: 2000,
    });

    const targets = React.useMemo(
        () =>
            (duplicates ?? []).filter(d =>
                d.analyzers.includes(AnalyzerName.DocUniqueId)
            ),
        [duplicates]
    );

    React.useEffect(() => {
        if (targets.length > 0) {
            setTargetId(targets[0].id);
        }
    }, [targets]);

    if (!isSuccess) {
        return <FullPageLoader />;
    }

    const onConfirm = async () => {
        if (!targetId) {
            return;
        }
        try {
            const updatedTarget = await addAsAssetVersion(asset.id, targetId);
            storeDelete(asset.id);
            storeUpdate(updatedTarget);
            toast.success(
                t(
                    'quarantine.add_as_version.success',
                    'File added as a new version'
                ) as string
            );
            onResolved?.();
        } catch (e) {
            toast.error(
                t(
                    'quarantine.add_as_version.error',
                    'Failed to add file as a new version'
                ) as string
            );
            throw e;
        }
    };

    return (
        <ConfirmDialog
            {...modalProps}
            title={t('quarantine.add_as_version.title', 'Add as a new version')}
            onConfirm={onConfirm}
            disabled={!targetId}
            confirmButtonProps={{
                startIcon: <LayersIcon />,
            }}
        >
            {targets.length === 0 ? (
                <Alert severity={'info'}>
                    {t(
                        'quarantine.add_as_version.no_target',
                        'No matching asset found to attach this file to.'
                    )}
                </Alert>
            ) : (
                <>
                    <Typography sx={{mb: 2}}>
                        {t(
                            'quarantine.add_as_version.description',
                            'This file shares a document unique ID with the asset(s) below. Choose the asset it should be attached to as a new source version — its current source becomes a previous version, and this quarantined asset is removed.'
                        )}
                    </Typography>
                    <RadioGroup
                        value={targetId ?? ''}
                        onChange={e => setTargetId(e.target.value)}
                    >
                        {targets.map(target => (
                            <Box
                                key={target.id}
                                sx={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 1.5,
                                    p: 1,
                                    borderRadius: 1,
                                    border: theme =>
                                        `1px solid ${
                                            targetId === target.id
                                                ? theme.palette.primary.main
                                                : theme.palette.divider
                                        }`,
                                    mb: 1,
                                    cursor: 'pointer',
                                }}
                                onClick={() => setTargetId(target.id)}
                            >
                                <Radio
                                    value={target.id}
                                    checked={targetId === target.id}
                                />
                                <Avatar
                                    variant={'rounded'}
                                    src={target.thumbnailUrl ?? undefined}
                                    sx={{width: 48, height: 48}}
                                >
                                    <ImageIcon />
                                </Avatar>
                                <Stack sx={{minWidth: 0}}>
                                    <Typography
                                        noWrap
                                        sx={{fontWeight: 500}}
                                        title={target.title ?? target.id}
                                    >
                                        {target.title ?? target.id}
                                    </Typography>
                                    {target.createdAt ? (
                                        <Typography
                                            variant={'body2'}
                                            color={'text.secondary'}
                                            noWrap
                                        >
                                            {new Date(
                                                target.createdAt
                                            ).toLocaleString()}
                                        </Typography>
                                    ) : null}
                                </Stack>
                            </Box>
                        ))}
                    </RadioGroup>
                </>
            )}
        </ConfirmDialog>
    );
}
