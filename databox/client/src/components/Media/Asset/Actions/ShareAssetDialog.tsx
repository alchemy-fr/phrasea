import React from 'react';
import {useTranslation} from 'react-i18next';
import {Asset, Share} from '../../../../types';
import {FormRow} from '@alchemy/react-form';
import {Button, ListItemText, Stack, Switch, TextField} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import FullPageLoader from '../../../Ui/FullPageLoader';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useModalFetch} from "../../../../hooks/useModalFetch.ts";
import {createAssetShare, getAssetShares, removeAssetShare} from "../../../../api/asset.ts";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import CloseIcon from "@mui/icons-material/Close";
import {useMutation} from "@tanstack/react-query";
import {queryClient} from "../../../../lib/query.ts";

type Props = {
    asset: Asset;
} & StackedModalProps;

export default function ShareAssetDialog({asset, open, modalIndex}: Props) {
    const {t} = useTranslation();
    const [checked, setChecked] = React.useState(false);
    const {closeModal} = useModals();
    const [advancedMode, setAdvancedMode] = React.useState(false);

    const queryKey = ['share', asset.id];

    const {data, isSuccess} = useModalFetch<Share[]>({
        queryKey,
        queryFn: () => getAssetShares(asset.id),
    });
    const publicShare: Share | undefined = data && data.length === 1 ? data[0] : undefined;
    const isSimple = !!publicShare || data && data.length === 0;

    React.useEffect(() => {
        setAdvancedMode(!isSimple);
    }, [isSimple]);

    React.useEffect(() => {
        setChecked(!!publicShare);
    }, [!publicShare]);

    const createShare = useMutation({
        mutationFn: async () => {
            setChecked(true);
            return await createAssetShare(asset.id);
        },
        onSuccess: (data) => {
            queryClient.setQueryData(queryKey, (prev: Share[]) => prev.concat([data]));
        },
    });

    const removeShare = useMutation({
        mutationFn: async () => {
            setChecked(false);
            if (publicShare) {
                return await removeAssetShare(publicShare.id);
            }
        },
        onSuccess: () => {
            queryClient.setQueryData(queryKey, (prev: Share[]) => prev.filter(i => i.id !== publicShare!.id));
        },
    });

    if (!isSuccess) {
        if (!open) {
            return null;
        }
        return <FullPageLoader/>;
    }

    const loading = createShare.isPending || removeShare.isPending;
    const publicUrl = publicShare ? `${window.location.origin}/share/${publicShare.id}/${publicShare.token}` : undefined;

    return (
        <FormDialog
            maxWidth={'sm'}
            modalIndex={modalIndex}
            open={open}
            title={t('share.dialog.title', 'Share Asset')}
            loading={loading}
            onSave={() => {
                if (publicUrl) {
                    navigator.clipboard.writeText(publicUrl);
                }
                closeModal();
            }}
            submitIcon={publicShare ? <ContentCopyIcon/> : <CloseIcon/>}
            submitLabel={publicShare ? t('share.dialog.submit', 'Copy Link') : t('common.close', 'Close')}
        >
            {advancedMode ? <>
                <FormRow>
                    <Stack direction={'row'}
                        alignItems={'center'}
                    >
                        <ListItemText
                            primary={t('share.dialog.create_public_link.primary', 'Create a public link')}
                            secondary={t('share.dialog.create_public_link.secondary', 'Share a link with anyone outside your organization')}
                        />
                        <Switch
                            disabled={loading}
                            checked={checked}
                            onChange={() => {
                                if (publicShare) {
                                    removeShare.mutate();
                                } else {
                                    createShare.mutate();
                                }
                            }}
                        />
                    </Stack>
                </FormRow>

                {publicShare && <FormRow>
                    <TextField
                        fullWidth
                        value={publicUrl}
                        InputProps={{
                            readOnly: true,
                        }}/>
                </FormRow>}
            </> : <>
                {data.map(share => (
                    <FormRow key={share.id}>
                        <TextField
                            fullWidth
                            value={`${window.location.origin}/share/${share.id}/${share.token}`}
                            InputProps={{
                                readOnly: true,
                            }}/>
                        <Button

                        >
                            {t('common.revoke', 'Revoke')}
                        </Button>
                    </FormRow>
                )}
            </>}
        </FormDialog>
    );
}
