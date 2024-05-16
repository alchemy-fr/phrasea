import React, {useEffect} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {Asset} from '../types';
import AssetProxy from './layouts/shared-components/AssetProxy';
import {loadAsset} from './api';
import PublicationSecurityProxy from './security/PublicationSecurityProxy';
import {logAssetView} from '../lib/log';
import ErrorPage from "./ErrorPage";

type Props = {
    id: string;
};

export default function EmbeddedAsset({id}: Props) {
    const [data, setData] = React.useState<Asset | undefined>();
    const [error, setError] = React.useState<string | undefined>();

    const load = React.useCallback(async () => {
        try {
            const asset = await loadAsset(id);
            setData(asset);
        } catch (e: any) {
            if ([403, 404, 401].includes(e.response?.status)) {
                setError(e.response.status.toString());
                return;
            } else {
                setError(e.toString());
            }
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

    if (error) {
        if (['401', '403'].includes(error)) {
            return <ErrorPage title={'Forbidden'} code={error} />;
        } else if ('404' === error) {
            return <ErrorPage title={'Not found'} code={error} />;
        }
    }

    if (!data) {
        return <FullPageLoader backdrop={false} />;
    }

    const {publication} = data;

    return (
        <>
            {publication && publication.authorized && (
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
            )}
            {publication && publication.cssLink ? (
                <link
                    rel="stylesheet"
                    type="text/css"
                    href={publication.cssLink}
                />
            ) : (
                ''
            )}
            <PublicationSecurityProxy publication={publication} reload={load}>
                {publication.authorized && (
                    <div className={'embedded-asset'}>
                        <AssetProxy
                            asset={data}
                            fluid={true}
                            isCurrent={true}
                        />
                    </div>
                )}
            </PublicationSecurityProxy>
        </>
    );
}
