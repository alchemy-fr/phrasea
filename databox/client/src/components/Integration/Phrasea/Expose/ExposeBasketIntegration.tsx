import {BasketIntegrationActionsProps, Integration} from "../../types.ts";
import {LoadingButton} from "@mui/lab";
import {useIntegrationData} from "../../useIntegrationData.ts";
import {IntegrationType, runBasketIntegrationAction} from "../../../../api/integrations.ts";
import {useIntegrationAuth} from "../../useIntegrationAuth.ts";
import SyncIcon from '@mui/icons-material/Sync';
import {Button, Card, CardActions, CardContent, Typography} from "@mui/material";
import {useModals} from '@alchemy/navigation';
import CreatePublicationDialog from "./CreatePublicationDialog.tsx";
import DeleteIcon from "@mui/icons-material/Delete";
import ConfirmDialog from "../../../Ui/ConfirmDialog.tsx";
import React from "react";
import {useChannelRegistration} from "../../../../lib/pusher.ts";
import {useTranslation} from 'react-i18next';

type Props = {} & BasketIntegrationActionsProps;

type SyncEvent = {
    id: string;
    action: string;
    done?: number;
    total?: number;
}

export default function ExposeBasketIntegration({
    integration,
    basket,
}: Props) {
    const {loading, requestAuth, hasValidToken} = useIntegrationAuth({
        integration,
    });
    const {t} = useTranslation();
    const [deleting, setDeleting] = React.useState<string | undefined>();
    const [syncForced, setSyncForced] = React.useState<string[]>([]);
    const {openModal} = useModals();
    const [syncStates, setSyncStates] = React.useState<Record<string, SyncEvent>>({});

    const {data, addData, removeData} = useIntegrationData({
        type: IntegrationType.Basket,
        integrationId: integration.id,
        objectId: basket.id,
        defaultData: integration.data,
    });

    useChannelRegistration(
        `basket-${basket.id}`,
        `integration:${Integration.PhraseaExpose}`,
        (event: SyncEvent) => {
            setSyncStates(p => ({
                ...p,
                [event.id]: event,
            }))
        }
    );

    const createPublication = () => {
        openModal(CreatePublicationDialog, {
            integrationId: integration.id,
            basket,
            onSuccess: addData,
        });
    }

    const deleteSync = async (id: string) => {
        openModal(ConfirmDialog, {
            confirmLabel: 'OK',
            title: 'Stop synchronization?',
            options: {
                deletePublication: `Also delete the Publication`,
            },
            onConfirm: async ({deletePublication}) => {
                setDeleting(id);
                try {
                    await runBasketIntegrationAction('stop', integration.id, basket.id, {
                        id,
                        deletePublication,
                    });
                    removeData(id);
                } finally {
                    setDeleting(undefined);
                }
            }
        });
    }

    const forceSync = async (id: string) => {
        setSyncForced(p => p.concat([id]));
        await runBasketIntegrationAction('force-sync', integration.id, basket.id, {
            id,
        });
    }

    const actionTr: Record<string, string> = {
        'sync-progress': t('integration.expose.sync.event.sync-complete', `Sync in progress…`),
        'sync-clean': t('integration.expose.sync.event.sync-complete', `Cleaning assets…`),
        'sync-complete': t('integration.expose.sync.event.sync-complete', `Sync Complete!`),
    }

    return <div>
        {!hasValidToken ? <div>
            <LoadingButton
                onClick={requestAuth}
                loading={loading}
                disabled={loading}
            >
                Authorize
            </LoadingButton>
        </div> : ''}

        {hasValidToken ? <Button
            startIcon={<SyncIcon/>}
            onClick={createPublication}
        >
            Sync with a Publication
        </Button> : ''}

        {data.pages.length > 0 && (
            data.pages.flat().map(d => {
                const {id, url} = d.value as { id: string; url: string };
                const syncState = syncStates[d.id];

                return <Card
                    key={d.id}
                >
                    <CardContent>
                        <Typography variant="h5" component="div">
                            <a
                                href={url}
                                target={'_blank'}
                            >#{id.substring(0, 6)}</a>
                        </Typography>
                    </CardContent>
                    <CardActions>
                        <LoadingButton
                            sx={{
                                mr: 1,
                            }}
                            onClick={() => forceSync(d.id)}
                            startIcon={<SyncIcon/>}
                            disabled={!hasValidToken || syncForced.includes(d.id)}
                        >
                            Force Sync
                        </LoadingButton>

                        <LoadingButton
                            sx={{
                                mr: 1,
                            }}
                            loading={deleting === d.id}
                            onClick={() => deleteSync(d.id)}
                            startIcon={<DeleteIcon/>}
                            disabled={!hasValidToken}
                        >
                            Delete
                        </LoadingButton>
                        <Typography variant="body2">
                            {syncState ? <>
                                {actionTr[syncState.action] ?? syncState.action}
                                {syncState.total ? <>
                                    {syncState.done}/{syncState.total}
                                </> : ''}
                            </> : ''}
                        </Typography>
                    </CardActions>
                </Card>
            })
        )}
    </div>
}
