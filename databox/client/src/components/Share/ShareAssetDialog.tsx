import React from 'react';
import {useTranslation} from 'react-i18next';
import {Asset, Share} from '../../types.ts';
import {FormRow} from '@alchemy/react-form';
import {
    Button,
    Divider,
    FormControlLabel,
    List,
    ListItemText,
    Stack,
    Switch,
} from '@mui/material';
import FormDialog from '../Dialog/FormDialog.tsx';
import FullPageLoader from '../Ui/FullPageLoader.tsx';
import {useModalFetch} from '../../hooks/useModalFetch.ts';
import {
    createAssetShare,
    getAssetShares,
    removeAssetShare,
} from '../../api/asset.ts';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import CloseIcon from '@mui/icons-material/Close';
import {useMutation} from '@tanstack/react-query';
import {queryClient} from '../../lib/query.ts';
import AddIcon from '@mui/icons-material/Add';
import CreateShareDialog from './CreateShareDialog.tsx';
import CopiableTextField from '../Ui/CopiableTextField.tsx';
import {toast} from 'react-toastify';
import ShareItem from './ShareItem.tsx';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {getShareTitle, UrlActions} from './UrlActions.tsx';
import {getShareUrl} from './shareUtils.ts';
import ShareSocials from './ShareSocials.tsx';

type Props = {
    asset: Asset;
} & StackedModalProps;

export default function ShareAssetDialog({asset, open, modalIndex}: Props) {
    const {t} = useTranslation();
    const [checked, setChecked] = React.useState(false);
    const {closeModal} = useModals();
    const [advancedMode, setAdvancedMode] = React.useState<
        boolean | undefined
    >();
    const [revoking, setRevoking] = React.useState<string[]>([]);
    const {openModal} = useModals();

    const queryKey = ['share', asset.id];

    const {data, isSuccess} = useModalFetch<Share[]>({
        queryKey,
        queryFn: () => getAssetShares(asset.id),
    });
    const publicShare: Share | undefined =
        data && data.length === 1 ? data[0] : undefined;
    const isSimple =
        (!!publicShare &&
            !publicShare.title &&
            !publicShare.expiresAt &&
            !publicShare.startsAt) ||
        (data && data.length === 0);

    React.useEffect(() => {
        if (isSuccess && advancedMode === undefined) {
            setAdvancedMode(!isSimple);
        }
    }, [isSimple, advancedMode, isSuccess]);

    React.useEffect(() => {
        setChecked(!!publicShare);
    }, [!publicShare]);

    const createShare = useMutation({
        mutationFn: async () => {
            setChecked(true);
            return await createAssetShare(asset.id);
        },
        onSuccess: data => {
            queryClient.setQueryData(queryKey, (prev: Share[]) =>
                [data].concat(prev)
            );
        },
    });

    const removeShare = useMutation({
        mutationFn: async (id: string) => {
            setChecked(false);
            setRevoking(p => [...p, id]);
            try {
                await removeAssetShare(id);

                return id;
            } finally {
                setRevoking(p => p.filter(i => i !== id));
            }
        },
        onSuccess: (id: string) => {
            queryClient.setQueryData(queryKey, (prev: Share[]) =>
                prev.filter(i => i.id !== id)
            );
        },
    });

    const createNew = () => {
        openModal(CreateShareDialog, {
            asset,
            onSuccess: (newShare: Share) => {
                queryClient.setQueryData(queryKey, (prev: Share[]) =>
                    prev.concat([newShare])
                );
            },
        });
    };

    if (!isSuccess) {
        if (!open) {
            return null;
        }
        return <FullPageLoader />;
    }

    const loading = createShare.isPending || removeShare.isPending;
    const publicUrl = publicShare ? getShareUrl(publicShare) : undefined;

    const copy = () => {
        if (publicUrl) {
            navigator.clipboard.writeText(publicUrl);
        }
    };

    return (
        <FormDialog
            maxWidth={advancedMode ? 'md' : 'sm'}
            modalIndex={modalIndex}
            open={open}
            title={t('share.dialog.title', 'Share Asset')}
            loading={loading}
            onSave={() => {
                if (!advancedMode && !!publicUrl) {
                    copy();
                    toast.success(
                        t('share.dialog.copied', 'Link copied to clipboard')
                    );
                }
                closeModal();
            }}
            submitIcon={
                !advancedMode && !!publicUrl ? (
                    <ContentCopyIcon />
                ) : (
                    <CloseIcon />
                )
            }
            submitLabel={
                !advancedMode && !!publicUrl
                    ? t('share.dialog.submit', 'Copy Link')
                    : t('dialog.close', 'Close')
            }
        >
            {!advancedMode ? (
                <>
                    <FormRow>
                        <Stack direction={'row'} alignItems={'center'}>
                            <ListItemText
                                primary={t(
                                    'share.dialog.create_public_link.primary',
                                    'Create a public link'
                                )}
                                secondary={t(
                                    'share.dialog.create_public_link.secondary',
                                    'Share a link with anyone outside your organization'
                                )}
                            />
                            <Switch
                                disabled={loading}
                                checked={checked}
                                onChange={() => {
                                    if (publicShare) {
                                        removeShare.mutate(publicShare.id);
                                    } else {
                                        createShare.mutate();
                                    }
                                }}
                            />
                        </Stack>
                    </FormRow>

                    {publicShare && (
                        <FormRow>
                            <CopiableTextField
                                fullWidth={true}
                                value={publicUrl!}
                                actions={<UrlActions url={publicUrl!} />}
                            />
                            <div>
                                <ShareSocials
                                    url={publicUrl!}
                                    title={getShareTitle(publicShare)}
                                    isImage={false}
                                />
                            </div>
                        </FormRow>
                    )}
                </>
            ) : (
                <>
                    {data.length > 0 ? (
                        <List
                            sx={{
                                mb: 2,
                            }}
                        >
                            {data.map((share: Share) => {
                                return (
                                    <ShareItem
                                        key={share.id}
                                        share={share}
                                        revoking={revoking.includes(share.id)}
                                        onRevoke={removeShare.mutate}
                                    />
                                );
                            })}
                        </List>
                    ) : (
                        ''
                    )}
                    <Button
                        variant={'contained'}
                        onClick={createNew}
                        startIcon={<AddIcon />}
                    >
                        {t('share.dialog.add_new', 'Create new Share Link')}
                    </Button>
                </>
            )}
            {isSimple && (
                <div>
                    <Divider
                        sx={{
                            my: 2,
                        }}
                    />
                    <FormControlLabel
                        control={
                            <Switch
                                checked={advancedMode || false}
                                onChange={() => setAdvancedMode(!advancedMode)}
                            />
                        }
                        label={t('share.dialog.advanced_mode', 'Advanced Mode')}
                    />
                </div>
            )}
        </FormDialog>
    );
}
