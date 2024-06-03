import {BasketIntegrationActionsProps} from "../../types.ts";
import {LoadingButton} from "@mui/lab";
import {useIntegrationData} from "../../useIntegrationData.ts";
import {IntegrationType, runBasketIntegrationAction} from "../../../../api/integrations.ts";
import {useIntegrationAuth} from "../../useIntegrationAuth.ts";
import SyncIcon from '@mui/icons-material/Sync';
import {Button} from "@mui/material";
import {useModals} from '@alchemy/navigation';
import CreatePublicationDialog from "./CreatePublicationDialog.tsx";
import DeleteIcon from "@mui/icons-material/Delete";
import ConfirmDialog from "../../../Ui/ConfirmDialog.tsx";
import React from "react";

type Props = {} & BasketIntegrationActionsProps;

export default function ExposeBasketIntegration({
    integration,
    basket,
}: Props) {
    const {loading, requestAuth, hasValidToken} = useIntegrationAuth({
        integration,
    });
    const [deleting, setDeleting] = React.useState<string | undefined>();
    const [syncForced, setSyncForced] = React.useState<string[]>([]);
    const {openModal} = useModals();

    const {data, addData, removeData} = useIntegrationData({
        type: IntegrationType.Basket,
        integrationId: integration.id,
        objectId: basket.id,
        defaultData: integration.data,
    });

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
                const {id, url} = d.value as {id: string; url: string};

                return <div
                    key={d.id}
                >
                    <a
                        href={url}
                        target={'_blank'}
                    >#{id.substring(0, 6)}</a>

                    <LoadingButton
                        sx={{
                            ml: 1,
                        }}
                        onClick={() => forceSync(d.id)}
                        startIcon={<SyncIcon/>}
                        disabled={syncForced.includes(d.id)}
                    >
                        Force Sync
                    </LoadingButton>

                    <LoadingButton
                        sx={{
                            ml: 1,
                        }}
                        loading={deleting === d.id}
                        onClick={() => deleteSync(d.id)}
                        startIcon={<DeleteIcon/>}
                    >
                        Delete
                    </LoadingButton>
                </div>
            })
        )}
    </div>
}
