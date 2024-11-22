import {ESDocumentState} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {getESDocument, syncESDocument} from '../../../api/asset';
import {useTranslation} from 'react-i18next';
import {useCallback, useEffect, useState} from "react";
import RefreshIcon from '@mui/icons-material/Refresh';
import {Alert, Button} from "@mui/material";
import {LoadingButton} from "@mui/lab";

type Props<T extends object> = {
    data: T;
    entity: string;
} & DialogTabProps;

export default function ESDocument<T>({
    data,
    onClose,
    minHeight,
    entity,
}: Props<T>) {
    const {t} = useTranslation();
    const [document, setDocument] = useState<ESDocumentState>();
    const [loading, setLoading] = useState(false);
    const [synced, setSynced] = useState(false);

    const refresh = useCallback(async () => {
        setLoading(true);
        try {
            setDocument(await getESDocument(entity, data.id));
        } finally {
            setLoading(false);
        }
    }, [data.id]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    const sync = async () => {
        setSynced(true);
        try {
            await syncESDocument(entity, data.id)
        } catch (e) {
            setSynced(false);
        }
    }

    return (
        <ContentTab
            loading={loading}
            disablePadding
            disableGutters
            onClose={onClose}
            minHeight={minHeight}
            actions={<>
                <LoadingButton
                    loading={loading}
                    disabled={loading}
                    onClick={refresh}
                    startIcon={<RefreshIcon/>}
                >
                    {t('es_document.refresh', 'Refresh')}
                </LoadingButton>
            </>}
        >

            {document ? <>
                {!document.synced ? <Alert severity={'warning'}
                                           action={<Button
                                               onClick={sync}
                                               disabled={synced}
                                           >
                                               {synced ? t('es_document.sync_scheduled', 'Sync scheduled') : t('asset.es_document.sync_now', 'Sync Now')}
                                           </Button>}
                >
                    {t('es_document.not_synced', 'This document is not synced.')}
                </Alert> : null}
                <pre style={{
                    fontSize: 12,
                }}>
                    {JSON.stringify(document.data, null, 4)}
                </pre>
            </> : null}
        </ContentTab>
    );
}
