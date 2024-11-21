import {Asset, StateSetter} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {getAssetESDocument} from '../../../api/asset';
import {useCallback, useEffect, useState} from "react";
import RefreshIcon from '@mui/icons-material/Refresh';
import {IconButton, LinearProgress, Typography} from "@mui/material";

type Props = {
    data: Asset;
    setData: StateSetter<Asset>;
} & DialogTabProps;

export default function AssetESDocument({
    data,
    onClose,
    minHeight,
}: Props) {
    const [document, setDocument] = useState<object>();
    const [loading, setLoading] = useState(false);

    const refresh = useCallback(async () => {
        setLoading(true);
        try {
            setDocument(await getAssetESDocument(data.id));
        } finally {
            setLoading(false);
        }
    }, [data.id]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            {loading && <LinearProgress/>}
            {document ? <>
                <IconButton onClick={refresh}>
                    <RefreshIcon />
                </IconButton>
                <pre style={{
                    fontSize: 12,
                }}>
                    {JSON.stringify(document, null, 4)}
                </pre>
            </> : null}
        </ContentTab>
    );
}
