import React, {useEffect} from 'react';
import Layout from "./Layout";
import FullPageLoader from "./FullPageLoader";
import {Asset} from "../types";
import AssetProxy from "./layouts/shared-components/AssetProxy";
import {loadAsset} from "./api";
import PublicationSecurityProxy from "./security/PublicationSecurityProxy";

type Props = {
    authenticated: boolean;
    id: string;
};

export default function EmbeddedAsset({
    authenticated,
    id,
}: Props) {

    const [data, setData] = React.useState<Asset | undefined>();

    const load = React.useCallback(async () => {
        const asset = await loadAsset(id);

        setData(asset);
    }, [id]);

    useEffect(() => {
        load();
    }, [load]);

    if (!data) {
        return <FullPageLoader/>
    }

    const {publication} = data;

    return <>
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
        {publication && publication.cssLink ? <link rel="stylesheet" type="text/css" href={publication.cssLink}/> : ''}
        <PublicationSecurityProxy
            publication={publication}
            reload={load}
        >
            {data.publication.authorized && <div
                className={'embedded-asset'}
            >
                <AssetProxy
                    asset={data}
                    fluid={true}
                />
            </div>}
        </PublicationSecurityProxy>
    </>
}
