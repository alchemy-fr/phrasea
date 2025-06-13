import {Entity, File} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {useTranslation} from 'react-i18next';
import {useCallback, useEffect, useState} from 'react';
import RefreshIcon from '@mui/icons-material/Refresh';
import {CircularProgress} from '@mui/material';
import {LoadingButton} from '@mui/lab';
import {getFileMetadata} from '../../../api/file.ts';

type Props<T extends Entity> = {} & DataTabProps<T>;

export default function FileMetadata<T extends Entity>({
    data,
    onClose,
    minHeight,
}: Props<T>) {
    const {t} = useTranslation();
    const [metadata, setMetadata] = useState<File['metadata']>();
    const [loading, setLoading] = useState(false);

    const refresh = useCallback(async () => {
        setLoading(true);
        try {
            setMetadata((await getFileMetadata(data.id)).metadata);
        } finally {
            setLoading(false);
        }
    }, [data.id]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    return (
        <ContentTab
            loading={loading}
            disablePadding
            disableGutters
            onClose={onClose}
            minHeight={minHeight}
            actions={
                <>
                    <LoadingButton
                        loading={loading}
                        disabled={loading}
                        onClick={refresh}
                        startIcon={<RefreshIcon />}
                    >
                        {t('file.dialog.metadata.refresh', 'Refresh')}
                    </LoadingButton>
                </>
            }
        >
            {!loading ? (
                <pre
                    style={{
                        fontSize: 12,
                    }}
                >
                    {JSON.stringify(metadata, null, 4)}
                </pre>
            ) : (
                <CircularProgress />
            )}
        </ContentTab>
    );
}
