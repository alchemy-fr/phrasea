import React, {useEffect} from 'react';
import {Asset} from '../../../types.ts';
import {loadAsset, logAssetView} from '../../../api/assetApi.ts';
import PublicationProxy from '../PublicationProxy.tsx';
import {Box} from '@mui/material';
import {FilePlayer} from '@alchemy/phrasea-framework';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';

type Props = {
    id: string;
};

export default function EmbeddedAsset({id}: Props) {
    const [data, setData] = React.useState<Asset | undefined>();
    const [errorCode, setErrorCode] = React.useState<number | undefined>();
    const [loading, setLoading] = React.useState(false);

    const {innerWidth: windowWidth, innerHeight: windowHeight} =
        useWindowSize();

    const load = React.useCallback(async () => {
        setLoading(true);
        try {
            const asset = await loadAsset(id);
            setData(asset);
        } catch (e: any) {
            if (e.response?.status) {
                setErrorCode(e.response.status);
            } else {
                setErrorCode(500);
            }
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => {
        load();
    }, [load]);

    useEffect(() => {
        if (data && data.publication.authorized) {
            logAssetView(data.id);
        }
    }, [data?.id]);

    return (
        <PublicationProxy
            publication={data?.publication}
            loading={loading}
            load={load}
            errorCode={errorCode}
        >
            <style>
                {`
                html {
                    height: 100%;
                }
                body, #root {
                    height: 100%;
                    overflow: hidden;
                }
            `}
            </style>
            <Box
                sx={{
                    'width': '100%',
                    'height': '100%',
                    'overflow': 'hidden',
                    '.asset-px': {
                        'height': '100%',
                        'width': '100%',
                        'img': {
                            maxWidth: '100%',
                            maxHeight: '100%',
                        },
                        '.video-container, .video-js, video': {
                            maxHeight: '100%',
                        },
                    },
                }}
            >
                {data ? (
                    <FilePlayer
                        file={{
                            id: data.id,
                            name: data.title ?? 'Asset',
                            type: data.mimeType,
                            url: data.previewUrl,
                        }}
                        controls={true}
                        title={data.title ?? 'Asset'}
                        dimensions={{
                            width: windowWidth,
                            height: windowHeight,
                        }}
                    />
                ) : (
                    <FullPageLoader />
                )}
            </Box>
        </PublicationProxy>
    );
}
